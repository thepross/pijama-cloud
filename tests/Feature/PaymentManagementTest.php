<?php

namespace Tests\Feature;

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private User $adminUser;

    private Role $vendorRole;

    private User $vendorUser;

    private Role $customerRole;

    private User $customerUser;

    private User $otherCustomerUser;

    private Role $distributorRole;

    private User $distributorUser;

    private Permiso $paymentsPermission;

    private Pedido $customerOrder;

    private Pedido $otherCustomerOrder;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Roles
        $this->adminRole = Role::create([
            'nombre' => 'Administrador',
            'descripcion' => 'System admin',
            'state' => 'activo',
        ]);

        $this->vendorRole = Role::create([
            'nombre' => 'Vendedor',
            'descripcion' => 'Seller',
            'state' => 'activo',
        ]);

        $this->customerRole = Role::create([
            'nombre' => 'Cliente',
            'descripcion' => 'Customer',
            'state' => 'activo',
        ]);

        $this->distributorRole = Role::create([
            'nombre' => 'Distribuidor',
            'descripcion' => 'Distributor',
            'state' => 'activo',
        ]);

        // Create Permission for Payments
        $this->paymentsPermission = Permiso::create([
            'nombre' => 'Pagos',
            'descripcion' => 'Registro de métodos de pago y cuotas',
            'ruta' => 'pagos',
            'icono' => 'CreditCard',
            'orden' => 5,
        ]);

        // Attach permissions (only Admin, Vendor, and Cliente have pagos)
        $this->adminRole->permissions()->attach($this->paymentsPermission->id);
        $this->vendorRole->permissions()->attach($this->paymentsPermission->id);
        $this->customerRole->permissions()->attach($this->paymentsPermission->id);

        // Create Users
        $this->adminUser = User::factory()->create([
            'id_rol' => $this->adminRole->id,
            'email' => 'admin@pijama.com',
            'state' => 'activo',
        ]);

        $this->vendorUser = User::factory()->create([
            'id_rol' => $this->vendorRole->id,
            'email' => 'vendedor@pijama.com',
            'state' => 'activo',
        ]);

        $this->customerUser = User::factory()->create([
            'id_rol' => $this->customerRole->id,
            'email' => 'cliente@pijama.com',
            'state' => 'activo',
        ]);

        $this->otherCustomerUser = User::factory()->create([
            'id_rol' => $this->customerRole->id,
            'email' => 'otro_cliente@pijama.com',
            'state' => 'activo',
        ]);

        $this->distributorUser = User::factory()->create([
            'id_rol' => $this->distributorRole->id,
            'email' => 'distribuidor@pijama.com',
            'state' => 'activo',
        ]);

        // Create Orders
        $this->customerOrder = Pedido::create([
            'id_cliente' => $this->customerUser->id,
            'fecha_pedido' => now()->toDateString(),
            'total' => 100.00,
            'estado_pedido' => 'pendiente',
            'state' => 'activo',
        ]);

        $this->otherCustomerOrder = Pedido::create([
            'id_cliente' => $this->otherCustomerUser->id,
            'fecha_pedido' => now()->toDateString(),
            'total' => 150.00,
            'estado_pedido' => 'confirmado',
            'state' => 'activo',
        ]);
    }

    public function test_guest_cannot_access_payments(): void
    {
        $this->get(route('pagos.index'))->assertRedirect('/login');
    }

    public function test_user_without_permission_cannot_access_payments(): void
    {
        $response = $this->actingAs($this->distributorUser)->get(route('pagos.index'));
        $response->assertStatus(403);
    }

    public function test_customer_can_view_only_own_payments_in_index(): void
    {
        // Payment 1 for customerUser
        $pago1 = Pago::create([
            'id_pedido' => $this->customerOrder->id,
            'monto' => 50.00,
            'fecha_pago' => now()->toDateString(),
            'tipo_pago' => 'efectivo',
            'estado_pago' => 'completado',
            'saldo_pendiente' => 50.00,
        ]);

        // Payment 2 for otherCustomerUser
        $pago2 = Pago::create([
            'id_pedido' => $this->otherCustomerOrder->id,
            'monto' => 70.00,
            'fecha_pago' => now()->toDateString(),
            'tipo_pago' => 'qr',
            'estado_pago' => 'pendiente',
            'saldo_pendiente' => 80.00,
        ]);

        $response = $this->actingAs($this->customerUser)->get(route('pagos.index'));
        $response->assertStatus(200);

        $pagosData = $response->original->getData()['page']['props']['pagos']['data'];
        $this->assertCount(1, $pagosData);
        $this->assertEquals($pago1->id, $pagosData[0]['id']);
    }

    public function test_staff_can_view_all_payments_in_index(): void
    {
        // Payment 1 for customerUser
        Pago::create([
            'id_pedido' => $this->customerOrder->id,
            'monto' => 50.00,
            'fecha_pago' => now()->toDateString(),
            'tipo_pago' => 'efectivo',
            'estado_pago' => 'completado',
            'saldo_pendiente' => 50.00,
        ]);

        // Payment 2 for otherCustomerUser
        Pago::create([
            'id_pedido' => $this->otherCustomerOrder->id,
            'monto' => 70.00,
            'fecha_pago' => now()->toDateString(),
            'tipo_pago' => 'qr',
            'estado_pago' => 'pendiente',
            'saldo_pendiente' => 80.00,
        ]);

        $response = $this->actingAs($this->vendorUser)->get(route('pagos.index'));
        $response->assertStatus(200);

        $pagosData = $response->original->getData()['page']['props']['pagos']['data'];
        $this->assertCount(2, $pagosData);
    }

    public function test_customer_can_register_payment(): void
    {
        $response = $this->actingAs($this->customerUser)->post(route('pagos.store'), [
            'id_pedido' => $this->customerOrder->id,
            'monto' => 40.00,
            'tipo_pago' => 'qr',
            'total_cuotas' => 2,
            'numero_cuota' => 1,
            'observacion' => 'Primer abono de cuota.',
        ]);

        // It redirects to show page for QR scan
        $pago = Pago::first();
        $this->assertNotNull($pago);
        $response->assertRedirect(route('pagos.show', $pago->id));

        $this->assertDatabaseHas('pagos', [
            'id_pedido' => $this->customerOrder->id,
            'monto' => 40.00,
            'tipo_pago' => 'qr',
            'estado_pago' => 'pendiente',
            'total_cuotas' => 2,
            'numero_cuota' => 1,
        ]);

        // Check audit log
        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->customerUser->id,
            'evento' => 'crear_pago',
        ]);
    }

    public function test_customer_cannot_exceed_pending_balance(): void
    {
        $response = $this->actingAs($this->customerUser)->post(route('pagos.store'), [
            'id_pedido' => $this->customerOrder->id,
            'monto' => 150.00, // Order total is $100.00
            'tipo_pago' => 'efectivo',
            'total_cuotas' => 1,
            'numero_cuota' => 1,
        ]);

        $response->assertSessionHasErrors(['monto']);
        $this->assertEquals(
            'El monto ingresado excede el saldo pendiente de $100.00',
            session('errors')->first('monto')
        );
    }

    public function test_customer_cannot_pay_for_another_customers_order(): void
    {
        $response = $this->actingAs($this->customerUser)->post(route('pagos.store'), [
            'id_pedido' => $this->otherCustomerOrder->id,
            'monto' => 50.00,
            'tipo_pago' => 'efectivo',
            'total_cuotas' => 1,
            'numero_cuota' => 1,
        ]);

        $response->assertSessionHasErrors(['id_pedido']);
        $this->assertEquals(
            'El pedido seleccionado no le pertenece.',
            session('errors')->first('id_pedido')
        );
    }

    public function test_validation_errors_are_in_spanish(): void
    {
        $response = $this->actingAs($this->customerUser)->post(route('pagos.store'), [
            'id_pedido' => '',
            'monto' => '',
            'tipo_pago' => 'invalido',
            'total_cuotas' => '',
            'numero_cuota' => '',
        ]);

        $response->assertSessionHasErrors(['id_pedido', 'monto', 'tipo_pago', 'total_cuotas', 'numero_cuota']);
        $this->assertEquals(
            'El pedido es obligatorio.',
            session('errors')->first('id_pedido')
        );
        $this->assertEquals(
            'El monto es obligatorio.',
            session('errors')->first('monto')
        );
        $this->assertEquals(
            'El tipo de pago seleccionado no es válido.',
            session('errors')->first('tipo_pago')
        );
    }

    public function test_customer_can_simulate_pagofacil_callback(): void
    {
        $pago = Pago::create([
            'id_pedido' => $this->customerOrder->id,
            'monto' => 60.00,
            'fecha_pago' => now()->toDateString(),
            'tipo_pago' => 'qr',
            'estado_pago' => 'pendiente',
            'total_cuotas' => 1,
            'numero_cuota' => 1,
            'saldo_pendiente' => 100.00,
        ]);

        $response = $this->actingAs($this->customerUser)->post(route('pagos.simular-callback', $pago->id));
        $response->assertRedirect(route('pagos.show', $pago->id));

        $pago->refresh();
        $this->assertEquals('completado', $pago->estado_pago);
        $this->assertEquals(40.00, $pago->saldo_pendiente);

        // Check audit log
        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->customerUser->id,
            'evento' => 'callback_pagofacil',
            'recurso' => 'pagos/'.$pago->id,
        ]);
    }

    public function test_staff_can_manually_confirm_cash_payment(): void
    {
        $pago = Pago::create([
            'id_pedido' => $this->customerOrder->id,
            'monto' => 100.00,
            'fecha_pago' => now()->toDateString(),
            'tipo_pago' => 'efectivo',
            'estado_pago' => 'pendiente',
            'total_cuotas' => 1,
            'numero_cuota' => 1,
            'saldo_pendiente' => 100.00,
        ]);

        $response = $this->actingAs($this->vendorUser)->put(route('pagos.update', $pago->id), [
            'estado_pago' => 'completado',
            'observacion' => 'Recibido en caja principal.',
        ]);

        $response->assertRedirect(route('pagos.show', $pago->id));

        $pago->refresh();
        $this->assertEquals('completado', $pago->estado_pago);
        $this->assertEquals(0.00, $pago->saldo_pendiente);
        $this->assertEquals('Recibido en caja principal.', $pago->observacion);

        // Check audit log
        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->vendorUser->id,
            'evento' => 'confirmar_pago',
            'recurso' => 'pagos/'.$pago->id,
        ]);
    }

    public function test_admin_can_delete_payment(): void
    {
        $pago = Pago::create([
            'id_pedido' => $this->customerOrder->id,
            'monto' => 100.00,
            'fecha_pago' => now()->toDateString(),
            'tipo_pago' => 'tarjeta',
            'estado_pago' => 'completado',
            'total_cuotas' => 1,
            'numero_cuota' => 1,
            'saldo_pendiente' => 0.00,
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('pagos.destroy', $pago->id));
        $response->assertRedirect(route('pagos.index'));

        $this->assertDatabaseMissing('pagos', [
            'id' => $pago->id,
        ]);

        // Check audit log
        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->adminUser->id,
            'evento' => 'eliminar_pago',
        ]);
    }

    public function test_seller_cannot_delete_pago(): void
    {
        $pago = Pago::create([
            'id_pedido' => $this->customerOrder->id,
            'monto' => 100.00,
            'fecha_pago' => now()->toDateString(),
            'tipo_pago' => 'tarjeta',
            'estado_pago' => 'completado',
            'total_cuotas' => 1,
            'numero_cuota' => 1,
            'saldo_pendiente' => 0.00,
        ]);

        $response = $this->actingAs($this->vendorUser)->delete(route('pagos.destroy', $pago->id));
        $response->assertStatus(403);
    }

    public function test_subsequent_payment_validation_fails_on_different_total_cuotas(): void
    {
        // First payment establishes 3 total cuotas
        Pago::create([
            'id_pedido' => $this->customerOrder->id,
            'monto' => 30.00,
            'fecha_pago' => now()->toDateString(),
            'tipo_pago' => 'efectivo',
            'estado_pago' => 'completado',
            'total_cuotas' => 3,
            'numero_cuota' => 1,
            'saldo_pendiente' => 70.00,
        ]);

        // Attempt second payment with different total cuotas (4)
        $response = $this->actingAs($this->customerUser)->post(route('pagos.store'), [
            'id_pedido' => $this->customerOrder->id,
            'monto' => 30.00,
            'tipo_pago' => 'qr',
            'total_cuotas' => 4,
            'numero_cuota' => 2,
        ]);

        $response->assertSessionHasErrors(['total_cuotas']);
        $this->assertEquals(
            'El total de cuotas para este pedido ya fue establecido en 3',
            session('errors')->first('total_cuotas')
        );
    }

    public function test_subsequent_payment_validation_fails_on_already_registered_numero_cuota(): void
    {
        // First payment
        Pago::create([
            'id_pedido' => $this->customerOrder->id,
            'monto' => 30.00,
            'fecha_pago' => now()->toDateString(),
            'tipo_pago' => 'efectivo',
            'estado_pago' => 'completado',
            'total_cuotas' => 3,
            'numero_cuota' => 1,
            'saldo_pendiente' => 70.00,
        ]);

        // Attempt another payment for cuota 1
        $response = $this->actingAs($this->customerUser)->post(route('pagos.store'), [
            'id_pedido' => $this->customerOrder->id,
            'monto' => 30.00,
            'tipo_pago' => 'qr',
            'total_cuotas' => 3,
            'numero_cuota' => 1,
        ]);

        $response->assertSessionHasErrors(['numero_cuota']);
        $this->assertEquals(
            'La cuota número 1 ya ha sido registrada.',
            session('errors')->first('numero_cuota')
        );
    }

    public function test_subsequent_payment_validation_succeeds_on_valid_remaining_numero_cuota(): void
    {
        // First payment
        Pago::create([
            'id_pedido' => $this->customerOrder->id,
            'monto' => 30.00,
            'fecha_pago' => now()->toDateString(),
            'tipo_pago' => 'efectivo',
            'estado_pago' => 'completado',
            'total_cuotas' => 3,
            'numero_cuota' => 1,
            'saldo_pendiente' => 70.00,
        ]);

        // Attempt second payment for cuota 2 (valid)
        $response = $this->actingAs($this->customerUser)->post(route('pagos.store'), [
            'id_pedido' => $this->customerOrder->id,
            'monto' => 30.00,
            'tipo_pago' => 'qr',
            'total_cuotas' => 3,
            'numero_cuota' => 2,
        ]);

        $pago = Pago::where('numero_cuota', 2)->first();
        $this->assertNotNull($pago);
        $response->assertRedirect(route('pagos.show', $pago->id));

        $this->assertDatabaseHas('pagos', [
            'id_pedido' => $this->customerOrder->id,
            'monto' => 30.00,
            'numero_cuota' => 2,
            'total_cuotas' => 3,
        ]);
    }
}
