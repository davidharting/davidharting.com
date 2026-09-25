<?php

use App\Mcp\Prompts\FileMedia;
use App\Mcp\Servers\AdminServer;
use App\Models\User;
use Tests\TestCase;

describe('shouldRegister()', function () {
    test('is listed for an admin', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        AdminServer::actingAs($admin)->prompts()->assertRegistered(FileMedia::class);
    });

    test('is not listed for a non-admin', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);

        AdminServer::actingAs($user)->prompts()->assertNotRegistered(FileMedia::class);
    });

    test('an anonymous caller cannot get the prompt at all', function () {
        /** @var TestCase $this */
        AdminServer::prompt(FileMedia::class)->assertHasErrors();
    });
});

describe('handle()', function () {
    test('takes the oldest task when no item is given', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        AdminServer::actingAs($admin)->prompt(FileMedia::class)
            ->assertOk()
            ->assertName('file-media')
            ->assertSee([
                'Take the oldest task in the File project.',
                'America/Indiana/Indianapolis',
                'Wait for my go-ahead.',
            ])
            ->assertDontSee('Find the task matching');
    });

    test('finds the named task when an item is given', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        AdminServer::actingAs($admin)->prompt(FileMedia::class, ['item' => 'Mean Streets'])
            ->assertOk()
            ->assertSee('Find the task matching "Mean Streets".')
            ->assertDontSee('Take the oldest task');
    });

    test('treats a blank item as no item', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        AdminServer::actingAs($admin)->prompt(FileMedia::class, ['item' => '  '])
            ->assertOk()
            ->assertSee('Take the oldest task in the File project.');
    });
});
