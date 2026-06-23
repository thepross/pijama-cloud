<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permiso;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private User $adminUser;
    private Role $customerRole;
    private User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Roles
        $this->adminRole = Role::create([
            'nombre' => 'Administrador',
            'descripcion' => 'System admin',
            'state' => 'activo',
        ]);

        $this->customerRole = Role::create([
            'nombre' => 'Cliente',
            'descripcion' => 'Customer',
            'state' => 'activo',
        ]);

        // Create Permissions
        $usersPermission = Permiso::create([
            'nombre' => 'Usuarios',
            'descripcion' => 'Manage users',
            'ruta' => 'usuarios',
            'icono' => 'Users',
            'orden' => 1,
        ]);

        $productsPermission = Permiso::create([
            'nombre' => 'Productos',
            'descripcion' => 'Manage products',
            'ruta' => 'productos',
            'icono' => 'Archive',
            'orden' => 2,
        ]);

        // Assign permissions
        $this->adminRole->permissions()->attach([$usersPermission->id, $productsPermission->id]);
        $this->customerRole->permissions()->attach($productsPermission->id);

        // Create Users
        $this->adminUser = User::factory()->create([
            'id_rol' => $this->adminRole->id,
            'nombre' => 'AdminJuan',
            'email' => 'admin@pijama.com',
        ]);

        $this->customerUser = User::factory()->create([
            'id_rol' => $this->customerRole->id,
            'nombre' => 'ClienteJuan',
            'email' => 'cliente@pijama.com',
        ]);

        // Create a product containing "Juan"
        Producto::create([
            'nombre' => 'Pijama Algodon Juan',
            'precio_compra' => 15,
            'precio_venta' => 25,
            'stock' => 10,
            'stock_minimo' => 2,
            'categoria' => 'Adultos',
            'state' => 'activo',
        ]);
    }

    public function test_guest_cannot_use_global_search(): void
    {
        $response = $this->getJson('/global-search?query=Juan');

        $response->assertStatus(401);
    }

    public function test_admin_receives_matching_users_and_products(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/global-search?query=Juan');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Admin has permission to view both 'usuarios' and 'productos'
        $this->assertCount(3, $data); // should find AdminJuan (user), ClienteJuan (user), and Pijama Algodon Juan (product)

        $types = collect($data)->pluck('type')->toArray();
        $this->assertContains('Usuario', $types);
        $this->assertContains('Producto', $types);
    }

    public function test_client_only_receives_matching_products_due_to_permissions(): void
    {
        $response = $this->actingAs($this->customerUser)->getJson('/global-search?query=Juan');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Client only has permission to view 'productos', so users must be excluded
        $this->assertCount(1, $data);
        $this->assertEquals('Producto', $data[0]['type']);
        $this->assertEquals('Pijama Algodon Juan', $data[0]['title']);
    }

    public function test_short_or_empty_queries_return_empty_results(): void
    {
        $response1 = $this->actingAs($this->adminUser)->getJson('/global-search?query=J');
        $response1->assertStatus(200);
        $response1->assertJsonCount(0);

        $response2 = $this->actingAs($this->adminUser)->getJson('/global-search?query=');
        $response2->assertStatus(200);
        $response2->assertJsonCount(0);
    }
}

