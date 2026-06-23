<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Oferta;
use App\Models\Bitacora;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private User $adminUser;
    private Role $customerRole;
    private User $customerUser;
    private Producto $product;
    private Permiso $ofertasPermission;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Roles
        $this->adminRole = Role::create([
            'nombre' => 'Administrador',
            'descripcion' => 'Administrador del sistema',
            'state' => 'activo',
        ]);

        $this->customerRole = Role::create([
            'nombre' => 'Cliente',
            'descripcion' => 'Cliente del negocio',
            'state' => 'activo',
        ]);

        // Create Permissions
        $this->ofertasPermission = Permiso::firstOrCreate(
            ['nombre' => 'Ofertas'],
            [
                'descripcion' => 'Registro y gestión de ofertas',
                'ruta' => 'ofertas',
                'icono' => 'Tag',
                'orden' => 11,
            ]
        );

        // Attach permissions (Only admin gets the ofertas permission)
        $this->adminRole->permissions()->attach($this->ofertasPermission->id);

        // Create Users
        $this->adminUser = User::factory()->create([
            'id_rol' => $this->adminRole->id,
            'email' => 'admin@pijama.com',
        ]);

        $this->customerUser = User::factory()->create([
            'id_rol' => $this->customerRole->id,
            'email' => 'cliente@pijama.com',
        ]);

        // Create a Sample Product
        $this->product = Producto::create([
            'codigo_qr' => 'QR-TEST-001',
            'nombre' => 'Pijama de Prueba',
            'descripcion' => 'Pijama de prueba para validar ofertas.',
            'color' => 'Gris',
            'talla' => 'L',
            'genero' => 'Unisex',
            'marca' => 'TestBrand',
            'material' => 'Algodón',
            'precio_compra' => 20.00,
            'precio_venta' => 40.00,
            'stock' => 10,
            'stock_minimo' => 2,
            'categoria' => 'Adultos',
            'state' => 'activo',
        ]);
    }

    /**
     * Test guest access is blocked.
     */
    public function test_guest_cannot_access_offers(): void
    {
        $response = $this->get('/ofertas');
        $response->assertRedirect('/login');

        $responsePost = $this->post('/ofertas', []);
        $responsePost->assertRedirect('/login');
    }

    /**
     * Test cliente role access is blocked.
     */
    public function test_cliente_cannot_access_offers(): void
    {
        $response = $this->actingAs($this->customerUser)->get('/ofertas');
        $response->assertStatus(403);

        $responsePost = $this->actingAs($this->customerUser)->post('/ofertas', [
            'id_producto' => $this->product->id,
            'nombre' => 'Super Oferta',
            'valor_descuento' => 10.00,
            'tipo_descuento' => 'porcentaje',
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addWeek()->toDateString(),
            'estado_oferta' => 'activa',
        ]);
        $responsePost->assertStatus(403);
    }

    /**
     * Test admin can access offers list.
     */
    public function test_admin_can_access_offers_list(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/ofertas');
        $response->assertStatus(200);
        $response->assertSee('ofertas\/Index', false);
    }

    /**
     * Test admin can create a new percentage offer.
     */
    public function test_admin_can_create_percentage_offer(): void
    {
        $startDate = now()->toDateString();
        $endDate = now()->addWeek()->toDateString();

        $response = $this->actingAs($this->adminUser)->post('/ofertas', [
            'id_producto' => $this->product->id,
            'nombre' => 'Oferta de Prueba Porcentaje',
            'descripcion' => 'Descuento del 20% en pijama de prueba.',
            'valor_descuento' => 20.00,
            'tipo_descuento' => 'porcentaje',
            'fecha_inicio' => $startDate,
            'fecha_fin' => $endDate,
            'estado_oferta' => 'activa',
        ]);

        $response->assertRedirect('/ofertas');
        $this->assertDatabaseHas('ofertas', [
            'id_producto' => $this->product->id,
            'nombre' => 'Oferta de Prueba Porcentaje',
            'valor_descuento' => 20.00,
            'tipo_descuento' => 'porcentaje',
            'fecha_inicio' => $startDate,
            'fecha_fin' => $endDate,
            'estado_oferta' => 'activa',
            'state' => 'activo',
        ]);

        // Verify audit log
        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->adminUser->id,
            'evento' => 'crear_oferta',
            'recurso' => 'ofertas',
        ]);
    }

    /**
     * Test admin can create a fixed amount offer.
     */
    public function test_admin_can_create_fixed_amount_offer(): void
    {
        $startDate = now()->toDateString();
        $endDate = now()->addWeek()->toDateString();

        $response = $this->actingAs($this->adminUser)->post('/ofertas', [
            'id_producto' => $this->product->id,
            'nombre' => 'Oferta de Prueba Monto',
            'descripcion' => 'Descuento de $5.00 en pijama de prueba.',
            'valor_descuento' => 5.00,
            'tipo_descuento' => 'monto',
            'fecha_inicio' => $startDate,
            'fecha_fin' => $endDate,
            'estado_oferta' => 'activa',
        ]);

        $response->assertRedirect('/ofertas');
        $this->assertDatabaseHas('ofertas', [
            'id_producto' => $this->product->id,
            'nombre' => 'Oferta de Prueba Monto',
            'valor_descuento' => 5.00,
            'tipo_descuento' => 'monto',
            'fecha_inicio' => $startDate,
            'fecha_fin' => $endDate,
            'estado_oferta' => 'activa',
            'state' => 'activo',
        ]);
    }

    /**
     * Test validator blocks invalid percentage discount values.
     */
    public function test_validation_blocks_percentage_above_hundred(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->from('/ofertas/create')
            ->post('/ofertas', [
                'id_producto' => $this->product->id,
                'nombre' => 'Oferta Imposible',
                'valor_descuento' => 105.00,
                'tipo_descuento' => 'porcentaje',
                'fecha_inicio' => now()->toDateString(),
                'fecha_fin' => now()->addWeek()->toDateString(),
                'estado_oferta' => 'activa',
            ]);

        $response->assertRedirect('/ofertas/create');
        $response->assertSessionHasErrors(['valor_descuento']);
        $this->assertDatabaseMissing('ofertas', ['nombre' => 'Oferta Imposible']);
    }

    /**
     * Test validator blocks invalid fixed amount discount values (amount >= sale price).
     */
    public function test_validation_blocks_fixed_amount_exceeding_sale_price(): void
    {
        // Sale price is 40.00. We try to discount 45.00.
        $response = $this->actingAs($this->adminUser)
            ->from('/ofertas/create')
            ->post('/ofertas', [
                'id_producto' => $this->product->id,
                'nombre' => 'Oferta Monto Excesivo',
                'valor_descuento' => 45.00,
                'tipo_descuento' => 'monto',
                'fecha_inicio' => now()->toDateString(),
                'fecha_fin' => now()->addWeek()->toDateString(),
                'estado_oferta' => 'activa',
            ]);

        $response->assertRedirect('/ofertas/create');
        $response->assertSessionHasErrors(['valor_descuento']);
        $this->assertDatabaseMissing('ofertas', ['nombre' => 'Oferta Monto Excesivo']);
    }

    /**
     * Test validation blocks invalid dates (fecha_fin < fecha_inicio).
     */
    public function test_validation_blocks_end_date_before_start_date(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->from('/ofertas/create')
            ->post('/ofertas', [
                'id_producto' => $this->product->id,
                'nombre' => 'Oferta Fechas Invalidas',
                'valor_descuento' => 10.00,
                'tipo_descuento' => 'porcentaje',
                'fecha_inicio' => now()->addWeek()->toDateString(),
                'fecha_fin' => now()->toDateString(),
                'estado_oferta' => 'activa',
            ]);

        $response->assertRedirect('/ofertas/create');
        $response->assertSessionHasErrors(['fecha_fin']);
        $this->assertDatabaseMissing('ofertas', ['nombre' => 'Oferta Fechas Invalidas']);
    }

    /**
     * Test admin can update an offer.
     */
    public function test_admin_can_update_offer(): void
    {
        $oferta = Oferta::create([
            'id_producto' => $this->product->id,
            'nombre' => 'Oferta Antigua',
            'descripcion' => 'Descripción antigua',
            'valor_descuento' => 10.00,
            'tipo_descuento' => 'porcentaje',
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addWeek()->toDateString(),
            'estado_oferta' => 'activa',
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->put("/ofertas/{$oferta->id}", [
                'id_producto' => $this->product->id,
                'nombre' => 'Oferta Modificada',
                'descripcion' => 'Descripción nueva',
                'valor_descuento' => 15.00,
                'tipo_descuento' => 'porcentaje',
                'fecha_inicio' => now()->toDateString(),
                'fecha_fin' => now()->addWeek()->toDateString(),
                'estado_oferta' => 'activa',
            ]);

        $response->assertRedirect('/ofertas');
        $this->assertDatabaseHas('ofertas', [
            'id' => $oferta->id,
            'nombre' => 'Oferta Modificada',
            'valor_descuento' => 15.00,
        ]);

        // Verify audit log
        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->adminUser->id,
            'evento' => 'editar_oferta',
            'recurso' => 'ofertas/' . $oferta->id,
        ]);
    }

    /**
     * Test admin can logically delete (deactivate) an offer.
     */
    public function test_admin_can_logically_delete_offer(): void
    {
        $oferta = Oferta::create([
            'id_producto' => $this->product->id,
            'nombre' => 'Oferta A Borrar',
            'valor_descuento' => 10.00,
            'tipo_descuento' => 'porcentaje',
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addWeek()->toDateString(),
            'estado_oferta' => 'activa',
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->delete("/ofertas/{$oferta->id}");

        $response->assertRedirect('/ofertas');
        $this->assertDatabaseHas('ofertas', [
            'id' => $oferta->id,
            'state' => 'inactivo',
        ]);

        // Verify audit log
        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->adminUser->id,
            'evento' => 'eliminar_oferta',
            'recurso' => 'ofertas/' . $oferta->id,
        ]);
    }
}
