<?php

use App\Models\User;
use Tests\TestCase;

describe('displayName()', function () {
    test('uses the display name when one is set', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['name' => 'Katie Harting', 'display_name' => 'Katie']);

        expect($user->displayName())->toBe('Katie');
    });

    test('falls back to the full name', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['name' => 'Katie Harting', 'display_name' => null]);

        expect($user->displayName())->toBe('Katie Harting');
    });
});
