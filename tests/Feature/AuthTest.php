<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_user_and_logs_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_password_is_hashed(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'hash@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'hash@example.com')->first();
        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(password_verify('password123', $user->password));
    }

    public function test_login_works_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

        $this->assertGuest();
    }

    public function test_guests_are_redirected_from_protected_pages(): void
    {
        foreach (['/dashboard', '/projects', '/findings', '/reports', '/settings'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/logout');
        $this->assertGuest();
    }
}
