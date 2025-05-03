<?php

use App\Models\User;
use Tests\TestCase;

describe('Pulse route auth', function () {
    test('anon user gets 404', function () {
        /** @var TestCase $this */
        $this->get('/pulse')->assertForbidden();
    });

    test('regular user gets 404', function () {
        /** @var TestCase $this */
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get('/pulse')
            ->assertForbidden();
    });

    test('admin user gets 200', function () {
        /** @var TestCase $this */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get('/pulse')
            ->assertOk();
    });
});
