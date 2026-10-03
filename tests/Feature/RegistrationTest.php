<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Desde el 24-sep-2026 el registro público está cerrado: las cuentas las crea el Admin (config/fortify.php). */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_closed()
    {
        $this->get('/register')->assertStatus(404);
    }

    public function test_nobody_can_register_by_post()
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(404);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
