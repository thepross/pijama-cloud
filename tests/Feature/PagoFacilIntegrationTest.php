<?php

namespace Tests\Feature;

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use App\Services\PagoFacilService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PagoFacilIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $customerUser;

    private Pedido $order;

    private Role $customerRole;

    private Permiso $paymentsPermission;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.pagofacil.service_token' => 'test-service-token',
            'services.pagofacil.secret_token' => 'test-secret-token',
            'services.pagofacil.base_url' => 'https://masterqr.pagofacil.com.bo/api/services/v2',
        ]);

        $this->customerRole = Role::create([
            'nombre' => 'Cliente',
            'descripcion' => 'Customer',
            'state' => 'activo',
        ]);

        $this->paymentsPermission = Permiso::create([
            'nombre' => 'Pagos',
            'descripcion' => 'Payments',
            'ruta' => 'pagos',
            'icono' => 'CreditCard',
            'orden' => 5,
        ]);

        // Sub permissions for payments
        Permiso::create([
            'nombre' => 'Ver Pagos',
            'descripcion' => 'Ver Pagos',
            'ruta' => 'pagos.ver',
            'id_padre' => $this->paymentsPermission->id,
            'orden' => 1,
            'state' => 'activo',
        ]);

        Permiso::create([
            'nombre' => 'Registrar Pagos',
            'descripcion' => 'Registrar Pagos',
            'ruta' => 'pagos.crear',
            'id_padre' => $this->paymentsPermission->id,
            'orden' => 2,
            'state' => 'activo',
        ]);

        $this->customerRole->permissions()->attach($this->paymentsPermission->id);
        $this->customerRole->permissions()->attach(Permiso::where('ruta', 'pagos.ver')->first()->id);
        $this->customerRole->permissions()->attach(Permiso::where('ruta', 'pagos.crear')->first()->id);

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

    public function test_service_login_authenticates_successfully(): void
    {
        Http::fake([
            'https://masterqr.pagofacil.com.bo/api/services/v2/login' => Http::response([
                'error' => 0,
                'status' => 1,
                'message' => 'Authentication successful.',
                'values' => [
                    'accessToken' => 'fake-jwt-token-12345',
                    'tokenType' => 'bearer',
                ],
            ], 200),
        ]);

        $service = new PagoFacilService;
        $token = $service->login();

        $this->assertEquals('fake-jwt-token-12345', $token);

        Http::assertSent(function ($request) {
            return $request->hasHeader('tcTokenService', 'test-service-token') &&
                   $request->hasHeader('tcTokenSecret', 'test-secret-token');
        });
    }

    public function test_store_creates_payment_and_fetches_real_qr(): void
    {
        Http::fake([
            'https://masterqr.pagofacil.com.bo/api/services/v2/login' => Http::response([
                'error' => 0,
                'status' => 1,
                'values' => ['accessToken' => 'fake-token'],
            ], 200),
            'https://masterqr.pagofacil.com.bo/api/services/v2/generate-qr' => Http::response([
                'error' => 0,
                'status' => 2007,
                'values' => [
                    'transactionId' => 99998888,
                    'qrBase64' => 'base64encodedqrdatahere',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->customerUser)->post(route('pagos.store'), [
            'id_pedido' => $this->order->id,
            'monto' => 100.00,
            'tipo_pago' => 'qr',
            'total_cuotas' => 1,
            'numero_cuota' => 1,
            'observacion' => 'Test payment',
        ]);

        // Assert redirect to show
        $pago = Pago::first();
        $this->assertNotNull($pago);
        $response->assertRedirect(route('pagos.show', $pago->id));

        // Assert DB has transaction details
        $this->assertEquals('99998888', $pago->transaction_id);
        $this->assertEquals('base64encodedqrdatahere', $pago->qr_base64);
        $this->assertEquals('pendiente', $pago->estado_pago);

        Http::assertSent(function ($request) {
            if ($request->url() === 'https://masterqr.pagofacil.com.bo/api/services/v2/generate-qr') {
                return $request->hasHeader('Authorization', 'Bearer fake-token') &&
                       $request['amount'] === 100.0 &&
                       $request['paymentMethod'] === 34;
            }

            return true;
        });
    }

    public function test_show_auto_checks_pago_facil_status_and_completes_payment(): void
    {
        // Setup existing pending QR payment
        $pago = Pago::create([
            'id_pedido' => $this->order->id,
            'monto' => 100.00,
            'fecha_pago' => now()->toDateString(),
            'tipo_pago' => 'qr',
            'estado_pago' => 'pendiente',
            'total_cuotas' => 1,
            'numero_cuota' => 1,
            'saldo_pendiente' => 100.00,
            'transaction_id' => '99998888',
            'qr_base64' => 'base64encodedqrdatahere',
        ]);

        Http::fake([
            'https://masterqr.pagofacil.com.bo/api/services/v2/login' => Http::response([
                'error' => 0,
                'status' => 1,
                'values' => ['accessToken' => 'fake-token'],
            ], 200),
            'https://masterqr.pagofacil.com.bo/api/services/v2/query-transaction' => Http::response([
                'error' => 0,
                'status' => 2008,
                'values' => [
                    'companyTransactionId' => "QR-PIJ-{$pago->id}",
                    'paymentStatus' => 3, // Completado
                    'amount' => '100.00',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->customerUser)->get(route('pagos.show', $pago->id));
        $response->assertStatus(200);

        // Assert payment is now completed in the database
        $pago->refresh();
        $this->assertEquals('completado', $pago->estado_pago);
        $this->assertEquals(0.00, (float) $pago->saldo_pendiente);

        Http::assertSent(function ($request) use ($pago) {
            if ($request->url() === 'https://masterqr.pagofacil.com.bo/api/services/v2/query-transaction') {
                return $request->hasHeader('Authorization', 'Bearer fake-token') &&
                       $request['companyTransactionId'] === "QR-PIJ-{$pago->id}";
            }

            return true;
        });
    }
}
