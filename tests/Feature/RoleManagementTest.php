<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permiso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private User $adminUser;
    private Role $customerRole;
    private User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Admin role with roles permission
        $this->adminRole = Role::create([
            'nombre' => 'Administrador',
            'descripcion' => 'System admin',
            'state' => 'activo',
        ]);

        $rolesPermission = Permiso::create([
            'nombre' => 'Roles',
            'descripcion' => 'Manage roles',
            'ruta' => 'roles',
            'icono' => 'Shield',
            'orden' => 1,
        ]);

        $this->adminRole->permissions()->attach($rolesPermission->id);

        $this->adminUser = User::factory()->create([
            'id_rol' => $this->adminRole->id,
            'email' => 'admin@pijama.com',
        ]);

        // Create Customer role (no permissions)
        $this->customerRole = Role::create([
            'nombre' => 'Cliente',
            'descripcion' => 'Customer role',
            'state' => 'activo',
        ]);

        $this->customerUser = User::factory()->create([
            'id_rol' => $this->customerRole->id,
            'email' => 'cliente@pijama.com',
        ]);
    }

    public function test_unauthorized_user_cannot_access_roles_index(): void
    {
        $response = $this->actingAs($this->customerUser)->get('/roles');

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_access_roles_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/roles');

        $response->assertStatus(200);
    }

    public function test_authorized_user_can_create_a_role(): void
    {
        $perm = Permiso::create([
            'nombre' => 'Test Perm',
            'ruta' => 'test',
            'icono' => 'Help',
        ]);

        $response = $this->actingAs($this->adminUser)->post('/roles', [
            'nombre' => 'Nuevo Rol Test',
            'descripcion' => 'Test description',
            'permissions' => [$perm->id],
        ]);

        $response->assertRedirect('/roles');
        $this->assertDatabaseHas('roles', [
            'nombre' => 'Nuevo Rol Test',
            'descripcion' => 'Test description',
            'state' => 'activo',
        ]);

        $newRole = Role::where('nombre', 'Nuevo Rol Test')->first();
        $this->assertTrue($newRole->permissions->contains($perm->id));
    }

    public function test_cannot_create_role_with_duplicate_name(): void
    {
        Role::create([
            'nombre' => 'Duplicado',
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->adminUser)->post('/roles', [
            'nombre' => 'Duplicado',
            'descripcion' => 'Diff desc',
            'permissions' => [],
        ]);

        $response->assertSessionHasErrors(['nombre', 'permissions']);
    }

    public function test_authorized_user_can_update_a_role(): void
    {
        $role = Role::create([
            'nombre' => 'Rol Modificable',
            'state' => 'activo',
        ]);

        $perm1 = Permiso::create(['nombre' => 'Perm 1', 'ruta' => 'p1', 'icono' => 'Help']);
        $perm2 = Permiso::create(['nombre' => 'Perm 2', 'ruta' => 'p2', 'icono' => 'Help']);

        $role->permissions()->attach($perm1->id);

        $response = $this->actingAs($this->adminUser)->put("/roles/{$role->id}", [
            'nombre' => 'Rol Modificado',
            'descripcion' => 'Updated desc',
            'permissions' => [$perm2->id], // Replace perm1 with perm2
        ]);

        $response->assertRedirect('/roles');
        $role->refresh();

        $this->assertSame('Rol Modificado', $role->nombre);
        $this->assertSame('Updated desc', $role->descripcion);
        $this->assertFalse($role->permissions->contains($perm1->id));
        $this->assertTrue($role->permissions->contains($perm2->id));
    }

    public function test_authorized_user_can_logically_delete_a_role(): void
    {
        $role = Role::create([
            'nombre' => 'Rol Borrable',
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/roles/{$role->id}");

        $response->assertRedirect('/roles');
        $role->refresh();

        $this->assertSame('inactivo', $role->state);
    }

    public function test_cannot_delete_essential_roles(): void
    {
        $response = $this->actingAs($this->adminUser)->delete("/roles/{$this->adminRole->id}");

        $response->assertRedirect('/roles');
        $this->adminRole->refresh();

        $this->assertSame('activo', $this->adminRole->state);
    }
}
