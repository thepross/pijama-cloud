<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Puntuacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRatingTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private User $adminUser;
    private Role $customerRole;
    private User $customerUser;
    private Producto $product;

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

        // Create Products Permission
        $productsPermission = Permiso::create([
            'nombre' => 'Productos',
            'descripcion' => 'Manage products',
            'ruta' => 'productos',
            'icono' => 'Archive',
            'orden' => 2,
        ]);

        // Attach permissions
        $this->adminRole->permissions()->attach($productsPermission->id);
        $this->customerRole->permissions()->attach($productsPermission->id);

        // Create Users
        $this->adminUser = User::factory()->create([
            'id_rol' => $this->adminRole->id,
            'email' => 'admin@pijama.com',
        ]);

        $this->customerUser = User::factory()->create([
            'id_rol' => $this->customerRole->id,
            'email' => 'cliente@pijama.com',
        ]);

        // Create a product
        $this->product = Producto::factory()->create([
            'state' => 'activo',
        ]);
    }

    public function test_guest_cannot_submit_rating(): void
    {
        $response = $this->post('/puntuaciones', [
            'id_producto' => $this->product->id,
            'puntuacion' => 5,
            'comentario' => 'Lindo diseño',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_customer_can_submit_rating_with_valid_data(): void
    {
        $response = $this->actingAs($this->customerUser)->post('/puntuaciones', [
            'id_producto' => $this->product->id,
            'puntuacion' => 5,
            'comentario' => 'Súper cómoda y fresca!',
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('puntuaciones', [
            'id_cliente' => $this->customerUser->id,
            'id_producto' => $this->product->id,
            'puntuacion' => 5,
            'comentario' => 'Súper cómoda y fresca!',
            'state' => 'activo',
        ]);

        // Verify audit log
        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->customerUser->id,
            'evento' => 'crear_puntuacion',
            'recurso' => 'productos/' . $this->product->id . '/puntuaciones',
        ]);
    }

    public function test_non_customer_cannot_submit_rating(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/puntuaciones', [
            'id_producto' => $this->product->id,
            'puntuacion' => 5,
            'comentario' => 'Intento de admin',
        ]);

        $response->assertStatus(403);
    }

    public function test_rating_validation_rules_enforced(): void
    {
        // Test score under minimum
        $response1 = $this->actingAs($this->customerUser)->post('/puntuaciones', [
            'id_producto' => $this->product->id,
            'puntuacion' => 0,
            'comentario' => 'Mal producto',
        ]);
        $response1->assertSessionHasErrors(['puntuacion']);
        $this->assertEquals(
            'La puntuación mínima es 1 estrella.',
            session('errors')->first('puntuacion')
        );

        // Test score over maximum
        $response2 = $this->actingAs($this->customerUser)->post('/puntuaciones', [
            'id_producto' => $this->product->id,
            'puntuacion' => 6,
            'comentario' => 'Excelente',
        ]);
        $response2->assertSessionHasErrors(['puntuacion']);
        $this->assertEquals(
            'La puntuación máxima es 5 estrellas.',
            session('errors')->first('puntuacion')
        );

        // Test non-existing product
        $response3 = $this->actingAs($this->customerUser)->post('/puntuaciones', [
            'id_producto' => 99999,
            'puntuacion' => 5,
        ]);
        $response3->assertSessionHasErrors(['id_producto']);
        $this->assertEquals(
            'El producto seleccionado no existe.',
            session('errors')->first('id_producto')
        );
    }

    public function test_ratings_are_correctly_aggregated_in_product_list(): void
    {
        // Create 3 reviews for the product
        Puntuacion::create([
            'id_cliente' => $this->customerUser->id,
            'id_producto' => $this->product->id,
            'puntuacion' => 3,
            'comentario' => 'Regular',
            'fecha_puntuacion' => now()->toDateString(),
            'state' => 'activo',
        ]);

        Puntuacion::create([
            'id_cliente' => $this->customerUser->id,
            'id_producto' => $this->product->id,
            'puntuacion' => 5,
            'comentario' => 'Excelente',
            'fecha_puntuacion' => now()->toDateString(),
            'state' => 'activo',
        ]);

        Puntuacion::create([
            'id_cliente' => $this->customerUser->id,
            'id_producto' => $this->product->id,
            'puntuacion' => 4,
            'comentario' => 'Buena',
            'fecha_puntuacion' => now()->toDateString(),
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->customerUser)->get('/productos');
        $response->assertStatus(200);

        // Assert Inertia shares the product with correctly computed average (4.0) and count (3)
        $response->assertInertia(function ($page) {
            $productData = $page->toArray()['props']['productos']['data'][0];
            $this->assertEquals(4.0, $productData['puntuaciones_avg_puntuacion']);
            $this->assertEquals(3, $productData['puntuaciones_count']);
            $this->assertCount(3, $productData['puntuaciones']);
        });
    }

    public function test_cannot_rate_inactive_product(): void
    {
        $inactiveProduct = Producto::factory()->create([
            'state' => 'inactivo',
        ]);

        $response = $this->actingAs($this->customerUser)->post('/puntuaciones', [
            'id_producto' => $inactiveProduct->id,
            'puntuacion' => 5,
        ]);

        $response->assertStatus(404);
    }

    public function test_admin_can_moderate_and_delete_rating(): void
    {
        $review = Puntuacion::create([
            'id_cliente' => $this->customerUser->id,
            'id_producto' => $this->product->id,
            'puntuacion' => 4,
            'comentario' => 'Comentario inapropiado',
            'fecha_puntuacion' => now()->toDateString(),
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/puntuaciones/{$review->id}");

        $response->assertRedirect();
        $review->refresh();

        $this->assertEquals('inactivo', $review->state);

        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->adminUser->id,
            'evento' => 'eliminar_puntuacion',
            'recurso' => "productos/{$review->id_producto}/puntuaciones/{$review->id}",
        ]);
    }

    public function test_client_cannot_moderate_or_delete_rating(): void
    {
        $review = Puntuacion::create([
            'id_cliente' => $this->customerUser->id,
            'id_producto' => $this->product->id,
            'puntuacion' => 4,
            'comentario' => 'Comentario normal',
            'fecha_puntuacion' => now()->toDateString(),
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->customerUser)->delete("/puntuaciones/{$review->id}");

        $response->assertStatus(403);
    }
}

