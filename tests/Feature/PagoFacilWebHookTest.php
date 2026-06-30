<?php

namespace Tests\Feature;

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagoFacilWebHookTest extends TestCase
{
    use RefreshDatabase;

    private User $customerUser;

    private Pedido $order;

    private Role $customerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerRole = Role::create([
            'nombre' => 'Cliente',
            'descripcion' => 'Customer',
            'state' => 'activo',
        ]);

        $this->customerUser = User::factory()->create([
            'id_rol' => $this->customerRole->id,
            'email' => 'cliente@pijama.com',
            'state' => 'activo',
        ]);

        $this->order = Pedido::create([
            'id_cliente' => $this->customerUser->id,
            'fecha_pedido' => now()->toDateString(),
            'total' => 100.00,
            'estado_pedido' => 'pendiente',
            'state' => 'activo',
        ]);
    }

    public function test_webhook_completes_payment_with_default_format(): void
    {
        $pago = Pago::create([
            'id_pedido' => $this->order->id,
            'monto' => 100.00,
            'fecha_pago' => now()->toDateString(),
            'tipo_pago' => 'qr',
            'estado_pago' => 'pendiente',
            'total_cuotas' => 1,
            'numero_cuota' => 1,
            'saldo_pendiente' => 100.00,
            'transaction_id' => 'tx-1234',
        ]);

        $response = $this->postJson(route('pagofacil.callback'), [
            'PedidoID' => "QR-PIJ-{$pago->id}-XYZ",
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['error' => 0]);

        $pago->refresh();
        $this->assertEquals('completado', $pago->estado_pago);
        $this->assertEquals(0, $pago->saldo_pendiente);

        $this->order->refresh();
        $this->assertEquals('confirmado', $this->order->estado_pedido);
    }

    public function test_webhook_completes_payment_with_venta_cuota_format(): void
    {
        $pago = Pago::create([
            'id_pedido' => $this->order->id,
            'monto' => 50.00,
            'fecha_pago' => now()->toDateString(),
            'tipo_pago' => 'qr',
            'estado_pago' => 'pendiente',
            'total_cuotas' => 2,
            'numero_cuota' => 1,
            'saldo_pendiente' => 100.00,
        ]);

        $response = $this->postJson(route('pagofacil.callback'), [
            'PedidoID' => "V{$this->order->id}-C1",
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['error' => 0]);

        $pago->refresh();
        $this->assertEquals('completado', $pago->estado_pago);
        $this->assertEquals(50, $pago->saldo_pendiente);
    }

    public function test_webhook_completes_payment_with_venta_only_format(): void
    {
        $pago = Pago::create([
            'id_pedido' => $this->order->id,
            'monto' => 100.00,
            'fecha_pago' => now()->toDateString(),
            'tipo_pago' => 'qr',
            'estado_pago' => 'pendiente',
            'total_cuotas' => 1,
            'numero_cuota' => 1,
            'saldo_pendiente' => 100.00,
        ]);

        $response = $this->postJson(route('pagofacil.callback'), [
            'PedidoID' => "V{$this->order->id}",
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['error' => 0]);

        $pago->refresh();
        $this->assertEquals('completado', $pago->estado_pago);
        $this->assertEquals(0, $pago->saldo_pendiente);
    }

    public function test_webhook_completes_payment_with_transaction_id(): void
    {
        $pago = Pago::create([
            'id_pedido' => $this->order->id,
            'monto' => 100.00,
            'fecha_pago' => now()->toDateString(),
            'tipo_pago' => 'qr',
            'estado_pago' => 'pendiente',
            'total_cuotas' => 1,
            'numero_cuota' => 1,
            'saldo_pendiente' => 100.00,
            'transaction_id' => 'trans-xyz-987',
        ]);

        $response = $this->postJson(route('pagofacil.callback'), [
            'TransactionID' => 'trans-xyz-987',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['error' => 0]);

        $pago->refresh();
        $this->assertEquals('completado', $pago->estado_pago);
        $this->assertEquals(0, $pago->saldo_pendiente);
    }
}
