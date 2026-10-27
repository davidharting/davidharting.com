<?php

use App\Models\User;
use Tests\TestCase;

test('redirects to login', function () {
    /** @var TestCase $this */
    $response = $this->get('/dashboard');
    $response->assertStatus(302);
});

test('renders for logged in user', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertOk();

    $response->assertSeeTextInOrder(['Account - davidharting.com', 'Profile']);
    $response->assertSee('Log out');
});

test('links the admin to Admin', function () {
    /** @var TestCase $this */
    $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/dashboard')
        ->assertSee(route('admin.index'));
});

test('does not link anyone else to Admin', function () {
    /** @var TestCase $this */
    $this->actingAs(User::factory()->create())->get('/dashboard')
        ->assertDontSee(route('admin.index'));
});
