<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_remember_me_creates_remember_cookie(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);

        // Verify that a remember cookie was created
        $response->assertCookie(
            Auth::getRecallerName(),
            null,
            false // Not encrypted
        );

        // Verify the remember_token was set in the database
        $user->refresh();
        $this->assertNotNull($user->remember_token);
    }

    public function test_remember_me_sets_remember_token_in_database(): void
    {
        $user = User::factory()->create(['remember_token' => null]);

        // Verify no remember token before login
        $this->assertNull($user->remember_token);

        // Login with remember me
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ]);

        // Verify the remember_token was set in the database
        $user->refresh();
        $this->assertNotNull($user->remember_token);
        $this->assertNotEmpty($user->remember_token);

        // Verify it's a 60-character string (Laravel's default)
        $this->assertEquals(60, strlen($user->remember_token));
    }

    public function test_login_without_remember_me_does_not_create_remember_cookie(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();

        // Verify no remember cookie was created
        $response->assertCookieMissing(Auth::getRecallerName());
    }
}
