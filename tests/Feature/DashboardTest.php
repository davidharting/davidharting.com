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

    $response->assertSeeTextInOrder(['Dashboard - davidharting.com', 'Profile']);
    $response->assertSee("Log out");
});
