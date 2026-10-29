<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

test('login screen can be rendered', function () {
    /** @var TestCase $this */
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('profile.edit', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('remember me creates remember cookie', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('profile.edit', absolute: false));

    // Verify that a remember cookie was created
    $response->assertCookie(
        Auth::getRecallerName(),
        null,
        false // Not encrypted
    );

    // Verify the remember_token was set in the database
    $user->refresh();
    $this->assertNotNull($user->remember_token);
});

test('remember me sets remember token in database', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['remember_token' => null]);

    // Verify no remember token before login
    $this->assertNull($user->remember_token);

    // Login with remember me
    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ]);

    // Verify the remember cookie was created
    $response->assertCookie(Auth::getRecallerName());

    // Verify the remember_token was set in the database
    $user->refresh();
    $this->assertNotNull($user->remember_token);
    $this->assertNotEmpty($user->remember_token);

    // Verify it's a 60-character string (Laravel's default)
    $this->assertEquals(60, strlen($user->remember_token));
});

test('login without remember me does not create remember cookie', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['remember_token' => null]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();

    // Verify no remember cookie was created
    $response->assertCookieMissing(Auth::getRecallerName());

    // Verify remember_token is still null in database
    $user->refresh();
    $this->assertNull($user->remember_token);
});
