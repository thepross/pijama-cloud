<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        \App\Models\Role::create([
            'nombre' => 'Cliente',
            'descripcion' => 'Cliente por defecto',
        ]);

        $response = $this->post('/register', [
            'username' => 'testuser',
            'nombre' => 'Test',
            'apellido' => 'User',
            'ci' => '12345678',
            'email' => 'test@example.com',
            'telefono' => '123456',
            'direccion' => 'Calle Falsa 123',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
