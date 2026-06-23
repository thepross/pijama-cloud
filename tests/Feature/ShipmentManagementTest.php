<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Pedido;
use App\Models\Envio;
use App\Models\Bitacora;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private User $adminUser;
    private Role $distRole;
    private User $distUser;
    private User $otherDistUser;
    private Role $customerRole;
    private User $customerUser;
    private Pedido $order;
    private Permiso $enviosPermission;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Roles
        $this->adminRole = Role::create([
            'nombre' => 'Administrador',
            'descripcion' => 'Admin',
            'state' => 'activo',
        ]);

        $this->distRole = Role::create([
            'nombre' => 'Distribuidor',
            'descripcion' => 'Distribuidor',
            'state' => 'activo',
        ]);

        $this->customerRole = Role::create([
            'nombre' => 'Cliente',
            'descripcion' => 'Cliente',
            'state' => 'activo',
        ]);

        // Create Permissions (We need envios permission)
        $this->enviosPermission = Permiso::create([
            'nombre' => 'Envíos',
            'descripcion' => 'Seguimiento y asignación de despachos',
            'ruta' => 'envios',
            'icono' => 'Truck',
            'orden' => 4,
        ]);

        // Also create Pedidos permission since it's checked
        $pedidosPermission = Permiso::create([
            'nombre' => 'Pedidos',
            'descripcion' => 'Pedidos',
            'ruta' => 'pedidos',
            'icono' => 'ShoppingBag',
            'orden' => 3,
        ]);

        // Attach permissions
        $this->adminRole->permissions()->attach([$this->enviosPermission->id, $pedidosPermission->id]);
        $this->distRole->permissions()->attach([$this->enviosPermission->id, $pedidosPermission->id]);
        $this->customerRole->permissions()->attach([$pedidosPermission->id]);

        // Create Users
        $this->adminUser = User::factory()->create([
            'id_rol' => $this->adminRole->id,
            'email' => 'admin@pijama.com',
        ]);

        $this->distUser = User::factory()->create([
            'id_rol' => $this->distRole->id,
            'email' => 'distribuidor@pijama.com',
        ]);

        $this->otherDistUser = User::factory()->create([
            'id_rol' => $this->distRole->id,
            'email' => 'distribuidor2@pijama.com',
        ]);

        $this->customerUser = User::factory()->create([
            'id_rol' => $this->customerRole->id,
            'email' => 'cliente@pijama.com',
            'direccion' => 'Av. Los Pinos 123',
        ]);

        // Create a base product
        $product = Producto::create([
            'codigo_qr' => 'QR-SHIP-001',
            'nombre' => 'Prenda',
            'precio_compra' => 10.00,
            'precio_venta' => 20.00,
            'stock' => 10,
            'stock_minimo' => 1,
            'categoria' => 'Adultos',
            'state' => 'activo',
        ]);

        // Create order for the customer
        $this->order = Pedido::create([
            'id_cliente' => $this->customerUser->id,
            'fecha_pedido' => now()->toDateString(),
            'total' => 20.00,
            'estado_pedido' => 'pendiente',
            'state' => 'activo',
        ]);
    }

    /**
     * Test guest cannot access dispatches.
     */
    public function test_guest_cannot_access_shipments(): void
    {
        $this->get('/envios')->assertRedirect('/login');
    }

    /**
     * Test customers cannot access dispatches.
     */
    public function test_customer_cannot_access_shipments(): void
    {
        $this->actingAs($this->customerUser)->get('/envios')->assertStatus(403);
    }

    /**
     * Test order status confirmed hook automatically creates shipment.
     */
    public function test_order_confirmation_creates_shipment(): void
    {
        // 1. Confirm the order
        $response = $this->actingAs($this->adminUser)->put("/pedidos/{$this->order->id}", [
            'estado_pedido' => 'confirmado',
        ]);

        $response->assertStatus(302);
        $this->order->refresh();
        $this->assertEquals('confirmado', $this->order->estado_pedido);

        // 2. Verify Envio record was created automatically
        $this->assertDatabaseHas('envios', [
            'id_pedido' => $this->order->id,
            'id_distribuidor' => null,
            'direccion_entrega' => 'Av. Los Pinos 123',
            'estado_envio' => 'pendiente',
            'state' => 'activo',
        ]);
    }

    /**
     * Test distributor can only see dispatches assigned to them.
     */
    public function test_distributor_only_views_assigned_shipments(): void
    {
        // Create two dispatches
        $envio1 = Envio::create([
            'id_pedido' => $this->order->id,
            'id_distribuidor' => $this->distUser->id,
            'direccion_entrega' => 'Calle A',
            'estado_envio' => 'pendiente',
            'state' => 'activo',
        ]);

        $envio2 = Envio::create([
            'id_pedido' => $this->order->id,
            'id_distribuidor' => $this->otherDistUser->id,
            'direccion_entrega' => 'Calle B',
            'estado_envio' => 'pendiente',
            'state' => 'activo',
        ]);

        // distUser logs in
        $response = $this->actingAs($this->distUser)->get('/envios');
        $response->assertStatus(200);
        $response->assertSee('envios\/Index', false);

        // distUser cannot edit envio2 (403)
        $responseEdit = $this->actingAs($this->distUser)->get("/envios/{$envio2->id}/edit");
        $responseEdit->assertStatus(403);

        // distUser can edit envio1
        $responseEditOwn = $this->actingAs($this->distUser)->get("/envios/{$envio1->id}/edit");
        $responseEditOwn->assertStatus(200);
    }

    /**
     * Test admin can assign a distributor and update address.
     */
    public function test_admin_can_assign_distributor(): void
    {
        $envio = Envio::create([
            'id_pedido' => $this->order->id,
            'direccion_entrega' => 'Original Address',
            'estado_envio' => 'pendiente',
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->adminUser)->put("/envios/{$envio->id}", [
            'id_distribuidor' => $this->distUser->id,
            'direccion_entrega' => 'New Delivery Address',
            'estado_envio' => 'en_camino',
        ]);

        $response->assertRedirect('/envios');
        $this->assertDatabaseHas('envios', [
            'id' => $envio->id,
            'id_distribuidor' => $this->distUser->id,
            'direccion_entrega' => 'New Delivery Address',
            'estado_envio' => 'en_camino',
        ]);

        // Check audit log
        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->adminUser->id,
            'evento' => 'asignar_distribuidor',
            'recurso' => 'envios/' . $envio->id,
        ]);
    }

    /**
     * Test updating shipment status to delivered automatically updates order to delivered.
     */
    public function test_delivered_shipment_syncs_delivered_order(): void
    {
        $envio = Envio::create([
            'id_pedido' => $this->order->id,
            'id_distribuidor' => $this->distUser->id,
            'direccion_entrega' => 'Dirección',
            'estado_envio' => 'en_camino',
            'state' => 'activo',
        ]);

        // distributor marks as delivered
        $response = $this->actingAs($this->distUser)->put("/envios/{$envio->id}", [
            'estado_envio' => 'entregado',
            'ruta' => 'Ruta Centro',
            'fecha_salida' => now()->subDay()->toDateString(),
            'fecha_entrega' => now()->toDateString(),
        ]);

        $response->assertRedirect('/envios');
        $envio->refresh();
        $this->assertEquals('entregado', $envio->estado_envio);

        // Check order status is synced!
        $this->order->refresh();
        $this->assertEquals('entregado', $this->order->estado_pedido);

        // Check audit log
        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->distUser->id,
            'evento' => 'actualizar_estado_envio',
            'recurso' => 'envios/' . $envio->id,
        ]);

        $this->assertDatabaseHas('bitacoras', [
            'evento' => 'actualizar_estado_pedido',
            'recurso' => 'pedidos/' . $this->order->id,
        ]);
    }

    /**
     * Test admin can logically delete a shipment.
     */
    public function test_admin_can_logically_delete_shipment(): void
    {
        $envio = Envio::create([
            'id_pedido' => $this->order->id,
            'direccion_entrega' => 'Dirección',
            'estado_envio' => 'pendiente',
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/envios/{$envio->id}");
        $response->assertRedirect('/envios');

        $envio->refresh();
        $this->assertEquals('inactivo', $envio->state);

        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->adminUser->id,
            'evento' => 'eliminar_envio',
        ]);
    }
}
