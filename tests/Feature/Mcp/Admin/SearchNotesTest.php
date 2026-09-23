<?php

use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\SearchNotes;
use App\Models\Note;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Mcp\Request;
use Tests\TestCase;

describe('shouldRegister()', function () {
    test('an anonymous caller cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $response = AdminServer::tool(SearchNotes::class, ['query' => 'xylophone']);

        // "Tool not found" rather than "denied": tools/call resolves through
        // the same filtered collection tools/list does, so a tool the caller
        // cannot register simply is not there.
        $response->assertHasErrors(['Tool [search-notes] not found']);
    });

    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);

        $response = AdminServer::actingAs($user)->tool(SearchNotes::class, ['query' => 'xylophone']);

        $response->assertHasErrors(['Tool [search-notes] not found']);
    });

    test('an admin can reach the tool', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(SearchNotes::class, ['query' => 'xylophone']);

        $response->assertOk();
    });
});

describe('handle()', function () {
    test('withholds drafts from a non-admin, even if it is reached', function () {
        /** @var TestCase $this */
        // Called directly, since going through the server would only prove
        // shouldRegister works. The point is that handle() refuses on its own
        // too, so registering this tool somewhere it does not belong leaks
        // nothing.
        Note::factory()->create(['title' => 'SECRET DRAFT xylophone', 'visible' => false]);
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new SearchNotes)->handle(new Request([
            'query' => 'xylophone',
            'include_drafts' => true,
        ]));

        expect($response->isError())->toBeTrue()
            ->and((string) $response->content())->toBe('You are not authorized to read David\'s notes.');
    });

    test('searches only published notes by default', function () {
        /** @var TestCase $this */
        Note::factory()->create(['title' => 'A published xylophone', 'visible' => true]);
        Note::factory()->create(['title' => 'SECRET DRAFT xylophone', 'visible' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(SearchNotes::class, ['query' => 'xylophone']);

        $response->assertOk();
        $response->assertDontSee('SECRET DRAFT');
        $response->assertStructuredContent(function ($json) {
            $json->where('total', 1)
                ->where('notes.0.status', 'published')
                ->etc();
        });
    });

    test('searches drafts too when asked, labelled as drafts', function () {
        /** @var TestCase $this */
        Note::factory()->create([
            'title' => 'A published xylophone',
            'published_at' => Carbon::create(2024, 1, 1),
            'visible' => true,
        ]);
        Note::factory()->create([
            'title' => 'A draft xylophone',
            'published_at' => Carbon::create(2025, 1, 1),
            'visible' => false,
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(SearchNotes::class, [
            'query' => 'xylophone',
            'include_drafts' => true,
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) {
            $json->where('total', 2)
                ->where('notes.0.title', 'A draft xylophone')
                ->where('notes.0.status', 'draft')
                ->where('notes.1.title', 'A published xylophone')
                ->where('notes.1.status', 'published')
                ->etc();
        });
    });

    test('includes a snippet of the surrounding content when the match is in the body', function () {
        /** @var TestCase $this */
        Note::factory()->create([
            'title' => 'Ordinary title',
            'markdown_content' => 'I finally bought a xylophone last week.',
            'visible' => false,
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(SearchNotes::class, [
            'query' => 'xylophone',
            'include_drafts' => true,
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) {
            $json->where('notes.0.snippet', 'I finally bought a xylophone last week.')->etc();
        });
    });

    test('rejects a query shorter than the minimum length', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(SearchNotes::class, ['query' => 'xyl']);

        $response->assertHasErrors(['query']);
    });

    test('rejects a non-boolean include_drafts', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(SearchNotes::class, [
            'query' => 'xylophone',
            'include_drafts' => 'yes please',
        ]);

        $response->assertHasErrors(['include drafts']);
    });
});
