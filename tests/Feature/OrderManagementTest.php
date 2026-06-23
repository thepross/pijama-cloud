<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Oferta;
use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Bitacora;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private User $adminUser;
    private Role $vendedorRole;
    private User $vendedorUser;
    private Role $customerRole;
    private User $customerUser;
    private User $otherCustomerUser;
    private Producto $product;
    private Permiso $pedidosPermission;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Roles
        $this->adminRole = Role::create([
            'nombre' => 'Administrador',
            'descripcion' => 'System admin',
            'state' => 'activo',
        ]);

        $this->vendedorRole = Role::create([
            'nombre' => 'Vendedor',
            'descripcion' => 'Vendedor',
            'state' => 'activo',
        ]);

        $this->customerRole = Role::create([
            'nombre' => 'Cliente',
            'descripcion' => 'Customer',
            'state' => 'activo',
        ]);

        // Create Pedidos Permission
        $this->pedidosPermission = Permiso::create([
            'nombre' => 'Pedidos',
            'descripcion' => 'Gestión de pedidos',
            'ruta' => 'pedidos',
            'icono' => 'ShoppingBag',
            'orden' => 3,
        ]);

        // Attach permissions
        $this->adminRole->permissions()->attach($this->pedidosPermission->id);
        $this->vendedorRole->permissions()->attach($this->pedidosPermission->id);
        $this->customerRole->permissions()->attach($this->pedidosPermission->id);

        // Create Users
        $this->adminUser = User::factory()->create([
            'id_rol' => $this->adminRole->id,
            'email' => 'admin@pijama.com',
        ]);

        $this->vendedorUser = User::factory()->create([
            'id_rol' => $this->vendedorRole->id,
            'email' => 'vendedor@pijama.com',
        ]);

        $this->customerUser = User::factory()->create([
            'id_rol' => $this->customerRole->id,
            'email' => 'cliente@pijama.com',
        ]);

        $this->otherCustomerUser = User::factory()->create([
            'id_rol' => $this->customerRole->id,
            'email' => 'cliente2@pijama.com',
        ]);

        // Create a Sample Product
        $this->product = Producto::create([
            'codigo_qr' => 'QR-ORD-001',
            'nombre' => 'Pijama Algodón Rayas',
            'descripcion' => 'Pijama de algodón con diseño a rayas.',
            'color' => 'Azul',
            'talla' => 'M',
            'genero' => 'Hombre',
            'marca' => 'PijamaCloud',
            'material' => 'Algodón',
            'precio_compra' => 12.00,
            'precio_venta' => 24.00,
            'stock' => 15,
            'stock_minimo' => 2,
            'categoria' => 'Adultos',
            'state' => 'activo',
        ]);
    }

    /**
     * Test guest cannot access orders.
     */
    public function test_guest_cannot_access_orders(): void
    {
        $this->get('/pedidos')->assertRedirect('/login');
        $this->post('/pedidos', [])->assertRedirect('/login');
    }

    /**
     * Test customer can view own orders and is blocked from others.
     */
    public function test_customer_order_visibility(): void
    {
        // Place an order for customer 1
        $pedido = Pedido::create([
            'id_cliente' => $this->customerUser->id,
            'fecha_pedido' => now()->toDateString(),
            'total' => 24.00,
            'estado_pedido' => 'pendiente',
            'state' => 'activo',
        ]);

        // Customer 1 can see it
        $this->actingAs($this->customerUser)->get('/pedidos')->assertStatus(200);
        $this->actingAs($this->customerUser)->get("/pedidos/{$pedido->id}")->assertStatus(200);

        // Customer 2 cannot see it
        $this->actingAs($this->otherCustomerUser)->get("/pedidos/{$pedido->id}")->assertStatus(403);
    }

    /**
     * Test staff can view all orders.
     */
    public function test_staff_can_view_all_orders(): void
    {
        $pedido = Pedido::create([
            'id_cliente' => $this->customerUser->id,
            'fecha_pedido' => now()->toDateString(),
            'total' => 24.00,
            'estado_pedido' => 'pendiente',
            'state' => 'activo',
        ]);

        $this->actingAs($this->vendedorUser)->get('/pedidos')->assertStatus(200);
        $this->actingAs($this->vendedorUser)->get("/pedidos/{$pedido->id}")->assertStatus(200);

        $this->actingAs($this->adminUser)->get('/pedidos')->assertStatus(200);
        $this->actingAs($this->adminUser)->get("/pedidos/{$pedido->id}")->assertStatus(200);
    }

    /**
     * Test placing a new order with stock checks and offer discounts.
     */
    public function test_customer_can_place_order_with_offer(): void
    {
        // 1. Create an active offer on the product (10% discount on 24.00 -> 2.40 discount -> 21.60 subtotal)
        Oferta::create([
            'id_producto' => $this->product->id,
            'nombre' => 'Descuento 10%',
            'descripcion' => 'Prueba',
            'valor_descuento' => 10.00,
            'tipo_descuento' => 'porcentaje',
            'fecha_inicio' => now()->subDay()->toDateString(),
            'fecha_fin' => now()->addWeek()->toDateString(),
            'estado_oferta' => 'activa',
            'state' => 'activo',
        ]);

        $initialStock = $this->product->stock; // 15

        $response = $this->actingAs($this->customerUser)->post('/pedidos', [
            'observacion' => 'Dejar en portería',
            'items' => [
                [
                    'id_producto' => $this->product->id,
                    'cantidad' => 2, // total price should be 2 * (24 - 2.40) = 43.20
                ]
            ]
        ]);

        $response->assertRedirect('/pedidos');

        // Check stock reduced
        $this->product->refresh();
        $this->assertEquals($initialStock - 2, $this->product->stock);

        // Check Pedido database record
        $this->assertDatabaseHas('pedidos', [
            'id_cliente' => $this->customerUser->id,
            'total' => 43.20,
            'estado_pedido' => 'pendiente',
            'observacion' => 'Dejar en portería',
            'state' => 'activo',
        ]);

        // Check DetallePedido record
        $this->assertDatabaseHas('detalle_pedido', [
            'id_producto' => $this->product->id,
            'cantidad' => 2,
            'precio_venta' => 24.00,
            'descuento' => 2.40,
            'subtotal' => 43.20,
        ]);

        // Check audit log
        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->customerUser->id,
            'evento' => 'crear_pedido',
            'recurso' => 'pedidos',
        ]);
    }

    /**
     * Test order placement fails if stock is insufficient.
     */
    public function test_insufficient_stock_fails_order_placement(): void
    {
        $response = $this->actingAs($this->customerUser)
            ->from('/pedidos/create')
            ->post('/pedidos', [
                'items' => [
                    [
                        'id_producto' => $this->product->id,
                        'cantidad' => 20, // stock is 15
                    ]
                ]
            ]);

        $response->assertRedirect('/pedidos/create');
        $response->assertSessionHasErrors(['items']);
        $this->assertDatabaseMissing('pedidos', ['id_cliente' => $this->customerUser->id]);
    }

    /**
     * Test client can cancel own pending order and stock is restored.
     */
    public function test_customer_can_cancel_own_pending_order(): void
    {
        // 1. Create order manually
        $pedido = Pedido::create([
            'id_cliente' => $this->customerUser->id,
            'fecha_pedido' => now()->toDateString(),
            'total' => 24.00,
            'estado_pedido' => 'pendiente',
            'state' => 'activo',
        ]);

        $detail = DetallePedido::create([
            'id_pedido' => $pedido->id,
            'id_producto' => $this->product->id,
            'cantidad' => 3,
            'precio_venta' => 24.00,
            'descuento' => 0.00,
            'subtotal' => 72.00,
            'state' => 'activo',
        ]);

        // Deduct stock manually to simulate placement
        $this->product->decrement('stock', 3); // stock is now 12
        $this->product->refresh();
        $this->assertEquals(12, $this->product->stock);

        // Cancel order
        $response = $this->actingAs($this->customerUser)->put("/pedidos/{$pedido->id}", [
            'estado_pedido' => 'cancelado',
        ]);

        $response->assertStatus(302);
        
        $pedido->refresh();
        $this->assertEquals('cancelado', $pedido->estado_pedido);

        // Verify stock is restored
        $this->product->refresh();
        $this->assertEquals(15, $this->product->stock);

        // Check audit log
        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->customerUser->id,
            'evento' => 'cancelar_pedido',
            'recurso' => 'pedidos/' . $pedido->id,
        ]);
    }

    /**
     * Test customer cannot cancel confirmed order.
     */
    public function test_customer_cannot_cancel_confirmed_order(): void
    {
        $pedido = Pedido::create([
            'id_cliente' => $this->customerUser->id,
            'fecha_pedido' => now()->toDateString(),
            'total' => 24.00,
            'estado_pedido' => 'confirmado',
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->customerUser)->put("/pedidos/{$pedido->id}", [
            'estado_pedido' => 'cancelado',
        ]);

        $pedido->refresh();
        $this->assertEquals('confirmado', $pedido->estado_pedido);
    }

    /**
     * Test staff can update order status.
     */
    public function test_staff_can_update_order_status(): void
    {
        $pedido = Pedido::create([
            'id_cliente' => $this->customerUser->id,
            'fecha_pedido' => now()->toDateString(),
            'total' => 24.00,
            'estado_pedido' => 'pendiente',
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->vendedorUser)->put("/pedidos/{$pedido->id}", [
            'estado_pedido' => 'confirmado',
            'observacion' => 'Confirmado por bodega',
        ]);

        $pedido->refresh();
        $this->assertEquals('confirmado', $pedido->estado_pedido);
        $this->assertEquals('Confirmado por bodega', $pedido->observacion);

        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->vendedorUser->id,
            'evento' => 'actualizar_estado_pedido',
        ]);
    }

    /**
     * Test admin can logically delete an order.
     */
    public function test_admin_can_logically_delete_order(): void
    {
        $pedido = Pedido::create([
            'id_cliente' => $this->customerUser->id,
            'fecha_pedido' => now()->toDateString(),
            'total' => 24.00,
            'estado_pedido' => 'pendiente',
            'state' => 'activo',
        ]);

        $detail = DetallePedido::create([
            'id_pedido' => $pedido->id,
            'id_producto' => $this->product->id,
            'cantidad' => 2,
            'precio_venta' => 24.00,
            'descuento' => 0.00,
            'subtotal' => 48.00,
            'state' => 'activo',
        ]);

        $this->product->decrement('stock', 2); // 13 stock

        $response = $this->actingAs($this->adminUser)->delete("/pedidos/{$pedido->id}");

        $response->assertRedirect('/pedidos');

        $pedido->refresh();
        $this->assertEquals('inactivo', $pedido->state);

        // Check stock restored
        $this->product->refresh();
        $this->assertEquals(15, $this->product->stock);

        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->adminUser->id,
            'evento' => 'eliminar_pedido',
        ]);
    }
}
