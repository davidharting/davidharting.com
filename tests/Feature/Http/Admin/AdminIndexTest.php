<?php

use App\Models\User;
use Tests\TestCase;

test('anonymous user not allowed', function () {
    /** @var TestCase $this */

    $response = $this->get('/backend');
    $response->assertStatus(403);
    $response->assertSeeText('Forbidden');
});

test('regular user not allowed', function () {
    /** @var TestCase $this */

    $user = User::factory()->create();
    $response = $this->actingAs($user)->get('/backend');
    $response->assertStatus(403);
    $response->assertSeeText('Forbidden');
});


test('Visible to admin', function () {
    /** @var TestCase $this */

    $user = User::factory()->admin()->create();
    $response = $this->actingAs($user)->get('/backend');
    $response->assertStatus(200);
    $response->assertSeeTextInOrder([
        'Admin',
        'Filament Admin',
        'Backup database'
    ]);
});
