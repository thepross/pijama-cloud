<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permiso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private User $adminUser;
    private Role $customerRole;
    private User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Admin role with usuarios permission
        $this->adminRole = Role::create([
            'nombre' => 'Administrador',
            'descripcion' => 'System admin',
            'state' => 'activo',
        ]);

        $usersPermission = Permiso::create([
            'nombre' => 'Usuarios',
            'descripcion' => 'Manage users',
            'ruta' => 'usuarios',
            'icono' => 'Users',
            'orden' => 1,
        ]);

        $this->adminRole->permissions()->attach($usersPermission->id);

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

    public function test_unauthorized_user_cannot_access_users_index(): void
    {
        $response = $this->actingAs($this->customerUser)->get('/usuarios');

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_access_users_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/usuarios');

        $response->assertStatus(200);
    }

    public function test_authorized_user_can_create_a_user(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/usuarios', [
            'username' => 'newuser',
            'nombre' => 'New',
            'apellido' => 'User',
            'ci' => '99999999',
            'email' => 'newuser@pijama.com',
            'telefono' => '77777777',
            'direccion' => 'Calle Nueva 123',
            'id_rol' => $this->customerRole->id,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/usuarios');
        $this->assertDatabaseHas('usuarios', [
            'username' => 'newuser',
            'email' => 'newuser@pijama.com',
            'ci' => '99999999',
            'id_rol' => $this->customerRole->id,
            'state' => 'activo',
        ]);
    }

    public function test_cannot_create_user_with_duplicate_fields(): void
    {
        User::factory()->create([
            'username' => 'dupuser',
            'email' => 'dup@pijama.com',
            'ci' => '11111111',
        ]);

        $response = $this->actingAs($this->adminUser)->post('/usuarios', [
            'username' => 'dupuser',
            'nombre' => 'Duplicate',
            'apellido' => 'User',
            'ci' => '11111111',
            'email' => 'dup@pijama.com',
            'id_rol' => '',
            'password' => 'password',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertSessionHasErrors(['username', 'email', 'ci', 'id_rol', 'password']);
    }

    public function test_authorized_user_can_update_a_user(): void
    {
        $targetUser = User::factory()->create([
            'id_rol' => $this->customerRole->id,
            'nombre' => 'OldName',
            'email' => 'old@pijama.com',
        ]);

        $response = $this->actingAs($this->adminUser)->put("/usuarios/{$targetUser->id}", [
            'username' => 'updated_username',
            'nombre' => 'NewName',
            'apellido' => $targetUser->apellido,
            'ci' => $targetUser->ci,
            'email' => 'new@pijama.com',
            'id_rol' => $this->adminRole->id, // Upgrade role to Admin
            'password' => '', // Keep existing password
            'password_confirmation' => '',
        ]);

        $response->assertRedirect('/usuarios');
        $targetUser->refresh();

        $this->assertSame('updated_username', $targetUser->username);
        $this->assertSame('NewName', $targetUser->nombre);
        $this->assertSame('new@pijama.com', $targetUser->email);
        $this->assertSame($this->adminRole->id, $targetUser->id_rol);
    }

    public function test_authorized_user_can_logically_delete_another_user(): void
    {
        $targetUser = User::factory()->create([
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/usuarios/{$targetUser->id}");

        $response->assertRedirect('/usuarios');
        $targetUser->refresh();

        $this->assertSame('inactivo', $targetUser->state);
    }

    public function test_user_cannot_delete_themselves(): void
    {
        $response = $this->actingAs($this->adminUser)->delete("/usuarios/{$this->adminUser->id}");

        $response->assertRedirect('/usuarios');
        $this->adminUser->refresh();

        $this->assertSame('activo', $this->adminUser->state);
    }
}
