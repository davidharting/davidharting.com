<?php

use Tests\TestCase;

test('registration screen is not available', function () {
    /** @var TestCase $this */
    $response = $this->get('/register');

    $response->assertNotFound();
});

test('new users cannot register', function () {
    /** @var TestCase $this */
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
});

test('registration screen can be rendered when reopened', function () {
    /** @var TestCase $this */
    config()->set('features.public_registration', true);

    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register when reopened', function () {
    /** @var TestCase $this */
    config()->set('features.public_registration', true);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('profile.edit', absolute: false));
});
