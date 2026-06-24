<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permiso;
use App\Models\Bitacora;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BitacoraManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private User $adminUser;

    private Role $customerRole;
    private User $customerUser;

    private Permiso $bitacorasPermission;

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

        // Create Permission for Bitacoras
        $this->bitacorasPermission = Permiso::create([
            'nombre' => 'Bitácora',
            'descripcion' => 'Registro de eventos y accesos del sistema',
            'ruta' => 'bitacoras',
            'icono' => 'History',
            'orden' => 8,
        ]);

        // Attach permissions
        $this->adminRole->permissions()->attach($this->bitacorasPermission->id);

        // Create Users
        $this->adminUser = User::factory()->create([
            'id_rol' => $this->adminRole->id,
            'email' => 'admin@pijama.com',
            'state' => 'activo',
        ]);

        $this->customerUser = User::factory()->create([
            'id_rol' => $this->customerRole->id,
            'email' => 'cliente@pijama.com',
            'state' => 'activo',
        ]);
    }

    public function test_guest_cannot_access_bitacoras(): void
    {
        $this->get('/bitacoras')->assertRedirect('/login');
    }

    public function test_user_without_permission_cannot_access_bitacoras(): void
    {
        $response = $this->actingAs($this->customerUser)->get('/bitacoras');
        $response->assertStatus(403);
    }

    public function test_authorized_user_can_access_bitacoras(): void
    {
        // Seed some sample logs
        Bitacora::create([
            'id_usuario' => $this->customerUser->id,
            'evento' => 'crear_producto',
            'ip' => '127.0.0.1',
            'recurso' => 'productos',
            'detalle' => '{"id":1,"nombre":"Pijama test"}',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/bitacoras');
        $response->assertStatus(200);

        // Assert that the page shares logs and KPIs
        $response->assertInertia(fn ($page) => $page
            ->component('bitacoras/Index')
            ->has('logs.data')
            ->has('usuarios')
            ->has('eventos_unicos')
            ->has('kpis.total_registros')
            ->has('kpis.acciones_modificacion')
        );

        // Assert it logged 'ver_bitacora' for admin
        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->adminUser->id,
            'evento' => 'ver_bitacora',
            'recurso' => 'bitacoras',
        ]);
    }

    public function test_authorized_user_can_filter_bitacoras_by_event(): void
    {
        // Seed sample logs
        Bitacora::create([
            'id_usuario' => $this->customerUser->id,
            'evento' => 'crear_producto',
            'ip' => '192.168.1.10',
            'recurso' => 'productos',
        ]);

        Bitacora::create([
            'id_usuario' => $this->adminUser->id,
            'evento' => 'eliminar_usuario',
            'ip' => '192.168.1.20',
            'recurso' => 'usuarios',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/bitacoras?evento=eliminar_usuario');
        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->where('filters.evento', 'eliminar_usuario')
        );
    }

    public function test_authorized_user_can_filter_bitacoras_by_user(): void
    {
        Bitacora::create([
            'id_usuario' => $this->customerUser->id,
            'evento' => 'crear_producto',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/bitacoras?user_id=' . $this->customerUser->id);
        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->where('filters.user_id', (string)$this->customerUser->id)
        );
    }
}
