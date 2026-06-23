<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permiso;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private User $adminUser;
    private Role $sellerRole;
    private User $sellerUser;
    private Role $customerRole;
    private User $customerUser;
    private Role $unauthorizedRole;
    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Roles
        $this->adminRole = Role::create([
            'nombre' => 'Administrador',
            'descripcion' => 'System admin',
            'state' => 'activo',
        ]);

        $this->sellerRole = Role::create([
            'nombre' => 'Vendedor',
            'descripcion' => 'Store seller',
            'state' => 'activo',
        ]);

        $this->customerRole = Role::create([
            'nombre' => 'Cliente',
            'descripcion' => 'Customer',
            'state' => 'activo',
        ]);

        $this->unauthorizedRole = Role::create([
            'nombre' => 'Unauthorized',
            'descripcion' => 'No permissions role',
            'state' => 'activo',
        ]);

        // Create Permission for Products
        $productsPermission = Permiso::create([
            'nombre' => 'Productos',
            'descripcion' => 'Manage textile products',
            'ruta' => 'productos',
            'icono' => 'Archive',
            'orden' => 2,
        ]);

        // Attach permissions
        $this->adminRole->permissions()->attach($productsPermission->id);
        $this->sellerRole->permissions()->attach($productsPermission->id);
        $this->customerRole->permissions()->attach($productsPermission->id);

        // Create Users
        $this->adminUser = User::factory()->create([
            'id_rol' => $this->adminRole->id,
            'email' => 'admin@pijama.com',
        ]);

        $this->sellerUser = User::factory()->create([
            'id_rol' => $this->sellerRole->id,
            'email' => 'vendedor@pijama.com',
        ]);

        $this->customerUser = User::factory()->create([
            'id_rol' => $this->customerRole->id,
            'email' => 'cliente@pijama.com',
        ]);

        $this->unauthorizedUser = User::factory()->create([
            'id_rol' => $this->unauthorizedRole->id,
            'email' => 'unauth@pijama.com',
        ]);
    }

    public function test_unauthorized_user_without_permission_cannot_access_products_index(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get('/productos');

        $response->assertStatus(403);
    }

    public function test_client_can_access_products_index(): void
    {
        $response = $this->actingAs($this->customerUser)->get('/productos');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('productos/Index'));
    }

    public function test_admin_and_seller_can_access_products_index(): void
    {
        $response1 = $this->actingAs($this->adminUser)->get('/productos');
        $response1->assertStatus(200);

        $response2 = $this->actingAs($this->sellerUser)->get('/productos');
        $response2->assertStatus(200);
    }

    public function test_client_cannot_access_create_form_or_create_product(): void
    {
        // View create form
        $response1 = $this->actingAs($this->customerUser)->get('/productos/create');
        $response1->assertStatus(403);

        // Post store request
        $response2 = $this->actingAs($this->customerUser)->post('/productos', [
            'nombre' => 'Test Product',
            'precio_compra' => 10,
            'precio_venta' => 15,
            'stock' => 20,
            'stock_minimo' => 5,
            'categoria' => 'Adultos',
        ]);
        $response2->assertStatus(403);
    }

    public function test_admin_and_seller_can_access_create_form(): void
    {
        $response1 = $this->actingAs($this->adminUser)->get('/productos/create');
        $response1->assertStatus(200);
        $response1->assertInertia(fn ($page) => $page->component('productos/Create'));

        $response2 = $this->actingAs($this->sellerUser)->get('/productos/create');
        $response2->assertStatus(200);
    }

    public function test_admin_can_create_a_product(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/productos', [
            'codigo_qr' => 'TEST-QR-123',
            'nombre' => 'Pijama Infantil Invierno',
            'descripcion' => 'Una pijama abrigada para niños',
            'color' => 'Azul',
            'talla' => '8',
            'genero' => 'Unisex',
            'marca' => 'PijamaCloud',
            'material' => 'Algodón',
            'precio_compra' => 45.50,
            'precio_venta' => 75.00,
            'stock' => 50,
            'stock_minimo' => 10,
            'categoria' => 'Niños',
            'foto' => 'https://example.com/pijama.jpg',
        ]);

        $response->assertRedirect('/productos');
        $response->assertSessionHas('success', 'Producto registrado exitosamente.');

        $this->assertDatabaseHas('productos', [
            'codigo_qr' => 'TEST-QR-123',
            'nombre' => 'Pijama Infantil Invierno',
            'precio_compra' => 45.50,
            'precio_venta' => 75.00,
            'stock' => 50,
            'categoria' => 'Niños',
            'state' => 'activo',
        ]);

        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->adminUser->id,
            'evento' => 'crear_producto',
            'recurso' => 'productos',
        ]);
    }

    public function test_seller_can_create_a_product(): void
    {
        $response = $this->actingAs($this->sellerUser)->post('/productos', [
            'nombre' => 'Pijama Vendedor Test',
            'precio_compra' => 30.00,
            'precio_venta' => 50.00,
            'stock' => 30,
            'stock_minimo' => 5,
            'categoria' => 'Jóvenes',
        ]);

        $response->assertRedirect('/productos');
        $this->assertDatabaseHas('productos', [
            'nombre' => 'Pijama Vendedor Test',
            'precio_compra' => 30.00,
            'precio_venta' => 50.00,
            'categoria' => 'Jóvenes',
        ]);
    }

    public function test_cannot_create_product_with_invalid_data(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/productos', [
            'nombre' => '',
            'precio_compra' => -10,
            'precio_venta' => -5,
            'stock' => -5,
            'stock_minimo' => -1,
            'categoria' => '',
        ]);

        $response->assertSessionHasErrors([
            'nombre',
            'precio_compra',
            'precio_venta', // Because precio_venta is gte:precio_compra, but purchase price of -10 is invalid anyway. Wait, let's verify exact error fields.
            'stock',
            'stock_minimo',
            'categoria',
        ]);

        // Let's also check selling price less than purchasing price explicitly
        $response2 = $this->actingAs($this->adminUser)->post('/productos', [
            'nombre' => 'Producto Invalido Precios',
            'precio_compra' => 50,
            'precio_venta' => 40, // Should fail: gte:precio_compra
            'stock' => 10,
            'stock_minimo' => 2,
            'categoria' => 'Adultos',
        ]);

        $response2->assertSessionHasErrors(['precio_venta']);

        // Check unique QR code constraint
        $existingProduct = Producto::factory()->create([
            'codigo_qr' => 'DUP-QR-CODE',
        ]);

        $response3 = $this->actingAs($this->adminUser)->post('/productos', [
            'codigo_qr' => 'DUP-QR-CODE',
            'nombre' => 'Producto Con QR Duplicado',
            'precio_compra' => 20,
            'precio_venta' => 30,
            'stock' => 10,
            'stock_minimo' => 2,
            'categoria' => 'Adultos',
        ]);

        $response3->assertSessionHasErrors(['codigo_qr']);
        $this->assertEquals(
            'Este código QR ya está registrado.',
            session('errors')->first('codigo_qr')
        );
    }

    public function test_client_cannot_edit_or_update_a_product(): void
    {
        $producto = Producto::factory()->create();

        $response1 = $this->actingAs($this->customerUser)->get("/productos/{$producto->id}/edit");
        $response1->assertStatus(403);

        $response2 = $this->actingAs($this->customerUser)->put("/productos/{$producto->id}", [
            'nombre' => 'Modificado Por Cliente',
            'precio_compra' => 10,
            'precio_venta' => 20,
            'stock' => 15,
            'stock_minimo' => 2,
            'categoria' => 'Adultos',
        ]);
        $response2->assertStatus(403);
    }

    public function test_admin_can_update_a_product(): void
    {
        $producto = Producto::factory()->create([
            'nombre' => 'Original Name',
            'precio_compra' => 15,
            'precio_venta' => 25,
        ]);

        $response = $this->actingAs($this->adminUser)->put("/productos/{$producto->id}", [
            'codigo_qr' => $producto->codigo_qr, // Keep same QR
            'nombre' => 'Updated Name',
            'precio_compra' => 20,
            'precio_venta' => 35,
            'stock' => $producto->stock,
            'stock_minimo' => $producto->stock_minimo,
            'categoria' => 'Jóvenes',
        ]);

        $response->assertRedirect('/productos');
        $producto->refresh();

        $this->assertEquals('Updated Name', $producto->nombre);
        $this->assertEquals(20.00, $producto->precio_compra);
        $this->assertEquals(35.00, $producto->precio_venta);
        $this->assertEquals('Jóvenes', $producto->categoria);

        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->adminUser->id,
            'evento' => 'modificar_producto',
            'recurso' => 'productos/' . $producto->id,
        ]);
    }

    public function test_client_cannot_delete_a_product(): void
    {
        $producto = Producto::factory()->create();

        $response = $this->actingAs($this->customerUser)->delete("/productos/{$producto->id}");
        $response->assertStatus(403);
    }

    public function test_admin_can_logically_delete_a_product(): void
    {
        $producto = Producto::factory()->create([
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/productos/{$producto->id}");

        $response->assertRedirect('/productos');
        $producto->refresh();

        $this->assertEquals('inactivo', $producto->state);

        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->adminUser->id,
            'evento' => 'eliminar_producto',
            'recurso' => 'productos/' . $producto->id,
        ]);
    }

    public function test_cannot_access_inactive_product_for_editing_or_updating(): void
    {
        $producto = Producto::factory()->create([
            'state' => 'inactivo',
        ]);

        $response1 = $this->actingAs($this->adminUser)->get("/productos/{$producto->id}/edit");
        $response1->assertStatus(404);

        $response2 = $this->actingAs($this->adminUser)->put("/productos/{$producto->id}", [
            'nombre' => 'Tratar de actualizar inactivo',
            'precio_compra' => 10,
            'precio_venta' => 20,
            'stock' => 15,
            'stock_minimo' => 2,
            'categoria' => 'Adultos',
        ]);
        $response2->assertStatus(404);
    }
}

