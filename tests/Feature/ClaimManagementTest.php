<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permiso;
use App\Models\Pedido;
use App\Models\Reclamo;
use App\Models\Bitacora;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaimManagementTest extends TestCase
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

    private Permiso $claimsPermission;
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

        // Create Permission for Claims
        $this->claimsPermission = Permiso::create([
            'nombre' => 'Reclamos',
            'descripcion' => 'Manage claims and comments',
            'ruta' => 'reclamos',
            'icono' => 'AlertTriangle',
            'orden' => 6,
        ]);

        // Attach permissions (only Admin, Vendor, Cliente have reclamos)
        $this->adminRole->permissions()->attach($this->claimsPermission->id);
        $this->vendorRole->permissions()->attach($this->claimsPermission->id);
        $this->customerRole->permissions()->attach($this->claimsPermission->id);

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
            'total' => 150.00,
            'estado_pedido' => 'pendiente',
            'state' => 'activo',
        ]);

        $this->otherCustomerOrder = Pedido::create([
            'id_cliente' => $this->otherCustomerUser->id,
            'fecha_pedido' => now()->toDateString(),
            'total' => 200.00,
            'estado_pedido' => 'confirmado',
            'state' => 'activo',
        ]);
    }

    public function test_guest_cannot_access_claims(): void
    {
        $this->get(route('reclamos.index'))->assertRedirect('/login');
    }

    public function test_user_without_permission_cannot_access_claims(): void
    {
        $response = $this->actingAs($this->distributorUser)->get(route('reclamos.index'));
        $response->assertStatus(403);
    }

    public function test_customer_can_view_only_own_claims_in_index(): void
    {
        // Create a claim for customerUser
        $claim1 = Reclamo::create([
            'id_cliente' => $this->customerUser->id,
            'tipo_reclamo' => 'Prenda defectuosa',
            'descripcion' => 'La costura está rota.',
            'fecha_reclamo' => now()->toDateString(),
            'state' => 'activo',
        ]);

        // Create a claim for otherCustomerUser
        $claim2 = Reclamo::create([
            'id_cliente' => $this->otherCustomerUser->id,
            'tipo_reclamo' => 'Retraso de envío',
            'descripcion' => 'Llegó muy tarde.',
            'fecha_reclamo' => now()->toDateString(),
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->customerUser)->get(route('reclamos.index'));
        $response->assertStatus(200);

        // Retrieve the data passed to the Inertia component
        $claims = $response->original->getData()['page']['props']['reclamos']['data'];

        $this->assertCount(1, $claims);
        $this->assertEquals($claim1->id, $claims[0]['id']);
    }

    public function test_staff_can_view_all_active_claims_in_index(): void
    {
        Reclamo::create([
            'id_cliente' => $this->customerUser->id,
            'tipo_reclamo' => 'Prenda defectuosa',
            'descripcion' => 'Detalle 1',
            'fecha_reclamo' => now()->toDateString(),
            'state' => 'activo',
        ]);

        Reclamo::create([
            'id_cliente' => $this->otherCustomerUser->id,
            'tipo_reclamo' => 'Retraso de envío',
            'descripcion' => 'Detalle 2',
            'fecha_reclamo' => now()->toDateString(),
            'state' => 'activo',
        ]);

        // Seller view
        $response = $this->actingAs($this->vendorUser)->get(route('reclamos.index'));
        $response->assertStatus(200);
        
        $claims = $response->original->getData()['page']['props']['reclamos']['data'];
        $this->assertCount(2, $claims);
    }

    public function test_customer_can_create_claim(): void
    {
        $response = $this->actingAs($this->customerUser)->post(route('reclamos.store'), [
            'tipo_reclamo' => 'Talla/Color incorrecto',
            'descripcion' => 'Pedí talla M y llegó S.',
            'id_pedido' => $this->customerOrder->id,
        ]);

        $response->assertRedirect(route('reclamos.index'));
        
        $this->assertDatabaseHas('reclamos', [
            'id_cliente' => $this->customerUser->id,
            'tipo_reclamo' => 'Talla/Color incorrecto',
            'descripcion' => 'Pedí talla M y llegó S.',
            'id_pedido' => $this->customerOrder->id,
            'estado_reclamo' => 'pendiente',
            'state' => 'activo',
        ]);

        // Assert audit log was recorded
        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->customerUser->id,
            'evento' => 'crear_reclamo',
        ]);
    }

    public function test_customer_cannot_link_claim_to_another_customers_order(): void
    {
        $response = $this->actingAs($this->customerUser)->post(route('reclamos.store'), [
            'tipo_reclamo' => 'Talla/Color incorrecto',
            'descripcion' => 'Pedí talla M y llegó S.',
            'id_pedido' => $this->otherCustomerOrder->id,
        ]);

        $response->assertSessionHasErrors(['id_pedido']);
        $this->assertEquals(
            'El pedido seleccionado no le pertenece o no está activo.',
            session('errors')->first('id_pedido')
        );
    }

    public function test_validation_errors_are_in_spanish(): void
    {
        $response = $this->actingAs($this->customerUser)->post(route('reclamos.store'), [
            'tipo_reclamo' => '',
            'descripcion' => '',
        ]);

        $response->assertSessionHasErrors(['tipo_reclamo', 'descripcion']);
        
        $this->assertEquals(
            'El tipo de reclamo es obligatorio.',
            session('errors')->first('tipo_reclamo')
        );
        $this->assertEquals(
            'La descripción es obligatoria.',
            session('errors')->first('descripcion')
        );
    }

    public function test_customer_cannot_view_another_customers_claim(): void
    {
        $claim = Reclamo::create([
            'id_cliente' => $this->otherCustomerUser->id,
            'tipo_reclamo' => 'Prenda defectuosa',
            'descripcion' => 'La costura está rota.',
            'fecha_reclamo' => now()->toDateString(),
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->customerUser)->get(route('reclamos.show', $claim->id));
        $response->assertStatus(403);
    }

    public function test_staff_can_view_any_claim(): void
    {
        $claim = Reclamo::create([
            'id_cliente' => $this->customerUser->id,
            'tipo_reclamo' => 'Prenda defectuosa',
            'descripcion' => 'La costura está rota.',
            'fecha_reclamo' => now()->toDateString(),
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->vendorUser)->get(route('reclamos.show', $claim->id));
        $response->assertStatus(200);
    }

    public function test_staff_can_respond_to_claim(): void
    {
        $claim = Reclamo::create([
            'id_cliente' => $this->customerUser->id,
            'tipo_reclamo' => 'Prenda defectuosa',
            'descripcion' => 'La costura está rota.',
            'fecha_reclamo' => now()->toDateString(),
            'estado_reclamo' => 'pendiente',
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->vendorUser)->put(route('reclamos.update', $claim->id), [
            'estado_reclamo' => 'atendido',
            'respuesta' => 'Se enviará una reposición de prenda hoy mismo.',
        ]);

        $response->assertRedirect(route('reclamos.show', $claim->id));

        $this->assertDatabaseHas('reclamos', [
            'id' => $claim->id,
            'estado_reclamo' => 'atendido',
            'respuesta' => 'Se enviará una reposición de prenda hoy mismo.',
            'fecha_respuesta' => now()->toDateString(),
        ]);

        // Assert audit log was recorded
        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->vendorUser->id,
            'evento' => 'atender_reclamo',
            'recurso' => 'reclamos/' . $claim->id,
        ]);
    }

    public function test_customer_cannot_respond_to_claim(): void
    {
        $claim = Reclamo::create([
            'id_cliente' => $this->customerUser->id,
            'tipo_reclamo' => 'Prenda defectuosa',
            'descripcion' => 'La costura está rota.',
            'fecha_reclamo' => now()->toDateString(),
            'estado_reclamo' => 'pendiente',
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->customerUser)->put(route('reclamos.update', $claim->id), [
            'estado_reclamo' => 'atendido',
            'respuesta' => 'Intento de respuesta por cliente.',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_logically_delete_claim(): void
    {
        $claim = Reclamo::create([
            'id_cliente' => $this->customerUser->id,
            'tipo_reclamo' => 'Prenda defectuosa',
            'descripcion' => 'La costura está rota.',
            'fecha_reclamo' => now()->toDateString(),
            'estado_reclamo' => 'pendiente',
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('reclamos.destroy', $claim->id));

        $response->assertRedirect(route('reclamos.index'));

        $this->assertDatabaseHas('reclamos', [
            'id' => $claim->id,
            'state' => 'inactivo',
        ]);

        // Assert audit log was recorded
        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->adminUser->id,
            'evento' => 'eliminar_reclamo',
            'recurso' => 'reclamos/' . $claim->id,
        ]);
    }

    public function test_seller_cannot_delete_claim(): void
    {
        $claim = Reclamo::create([
            'id_cliente' => $this->customerUser->id,
            'tipo_reclamo' => 'Prenda defectuosa',
            'descripcion' => 'La costura está rota.',
            'fecha_reclamo' => now()->toDateString(),
            'estado_reclamo' => 'pendiente',
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->vendorUser)->delete(route('reclamos.destroy', $claim->id));
        $response->assertStatus(403);
    }
}
