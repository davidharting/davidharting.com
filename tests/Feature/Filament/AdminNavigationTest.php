<?php

use App\Models\User;
use Tests\TestCase;

test('groups the sidebar into Writing, Media Library and OAuth, in that order', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSeeTextInOrder([
            'Writing',
            'Notes',
            'Pages',
            'Media Library',
            'Media',
            'Creators',
            'Media Events',
            'OAuth',
            'Clients',
            'Tokens',
        ]);
});
