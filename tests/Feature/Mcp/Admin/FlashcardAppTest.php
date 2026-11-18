<?php

use App\Mcp\Resources\FlashcardApp;
use App\Mcp\Servers\AdminServer;
use App\Models\User;
use App\Models\Word;
use Tests\TestCase;

describe('shouldRegister()', function () {
    test('an anonymous caller cannot read the app', function () {
        /** @var TestCase $this */
        AdminServer::resources()->assertNotRegistered(FlashcardApp::class);
    });

    test('a non-admin cannot read the app', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);

        AdminServer::actingAs($user)->resources()->assertNotRegistered(FlashcardApp::class);
    });

    test('an admin can list the app', function () {
        /** @var TestCase $this */
        AdminServer::actingAs(User::factory()->admin()->create())->resources()->assertRegistered(FlashcardApp::class);
    });
});

describe('handle()', function () {
    test('serves the card shell, which fetches and grades through the practice tools', function () {
        /** @var TestCase $this */
        $response = AdminServer::actingAs(User::factory()->admin()->create())->resource(FlashcardApp::class);

        $response->assertOk();
        $response->assertSee('createMcpApp');
        $response->assertSee("call('next-flashcard'");
        $response->assertSee("call('reveal-flashcard'");
        $response->assertSee("call('grade-flashcard'");
    });

    test('puts no word or definition in the HTML', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'ephemeral', 'short_definition' => 'lasting a very short time']);

        $response = AdminServer::actingAs(User::factory()->admin()->create())->resource(FlashcardApp::class);

        $response->assertOk();
        $response->assertDontSee('ephemeral');
        $response->assertDontSee('lasting a very short time');
    });
});

describe('toArray()', function () {
    test('is served as an MCP app under the ui scheme', function () {
        /** @var TestCase $this */
        $app = new FlashcardApp;

        expect($app->toArray()['mimeType'])->toBe('text/html;profile=mcp-app')
            ->and($app->uri())->toStartWith('ui://');
    });
});
