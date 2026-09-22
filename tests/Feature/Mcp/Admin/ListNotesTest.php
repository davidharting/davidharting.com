<?php

use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\ListNotes;
use App\Models\Note;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Mcp\Request;
use Tests\TestCase;

describe('shouldRegister()', function () {
    test('an anonymous caller cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $response = AdminServer::tool(ListNotes::class);

        // "Not found" rather than "denied" is the expected wording: tools/call
        // resolves through the same filtered collection tools/list does, so a
        // tool the caller cannot register simply is not there.
        $response->assertHasErrors(['not found']);
    });

    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);

        $response = AdminServer::actingAs($user)->tool(ListNotes::class);

        $response->assertHasErrors(['not found']);
    });

    test('an admin can reach the tool', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(ListNotes::class);

        $response->assertOk();
    });
});

describe('handle()', function () {
    test('refuses a caller the note policy denies, even if it is reached', function () {
        /** @var TestCase $this */
        // Called directly, since going through the server would only prove
        // shouldRegister works. The point is that handle() refuses on its own
        // too, so registering this tool somewhere it does not belong leaks
        // nothing.
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new ListNotes)->handle(new Request);

        expect($response->isError())->toBeTrue()
            ->and((string) $response->content())->toBe('You are not authorized to read David\'s notes.');
    });

    test('excludes drafts by default', function () {
        /** @var TestCase $this */
        Note::factory()->create(['title' => 'Published note', 'visible' => true]);
        Note::factory()->create(['title' => 'SECRET DRAFT', 'visible' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(ListNotes::class);

        $response->assertOk();
        $response->assertDontSee('SECRET DRAFT');
        $response->assertStructuredContent(function ($json) {
            $json->where('total', 1)
                ->where('notes.0.status', 'published')
                ->etc();
        });
    });

    test('includes drafts when asked, labelled as drafts', function () {
        /** @var TestCase $this */
        Note::factory()->create([
            'title' => 'Published note',
            'published_at' => Carbon::create(2024, 1, 1),
            'visible' => true,
        ]);
        Note::factory()->create([
            'title' => 'Draft note',
            'published_at' => Carbon::create(2025, 1, 1),
            'visible' => false,
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(ListNotes::class, ['include_drafts' => true]);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) {
            $json->where('total', 2)
                ->where('notes.0.title', 'Draft note')
                ->where('notes.0.status', 'draft')
                ->where('notes.1.title', 'Published note')
                ->where('notes.1.status', 'published')
                ->etc();
        });
    });

    test('includes slug, title, lead, published_at, url, and status for each note', function () {
        /** @var TestCase $this */
        $note = Note::factory()->create([
            'title' => 'My Note',
            'lead' => 'A lead paragraph',
            'visible' => true,
            'published_at' => Carbon::create(2024, 6, 1, 12),
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(ListNotes::class);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) use ($note) {
            $json->where('notes.0.slug', $note->slug)
                ->where('notes.0.title', 'My Note')
                ->where('notes.0.lead', 'A lead paragraph')
                ->where('notes.0.published_at', $note->published_at->toIso8601String())
                ->where('notes.0.url', route('notes.show', $note->slug))
                ->where('notes.0.status', 'published')
                ->etc();
        });
    });

    test('excludes markdown content from list responses', function () {
        /** @var TestCase $this */
        Note::factory()->create([
            'visible' => false,
            'markdown_content' => 'UNIQUE-BODY-CONTENT-MARKER',
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(ListNotes::class, ['include_drafts' => true]);

        $response->assertOk();
        $response->assertDontSee('UNIQUE-BODY-CONTENT-MARKER');
    });

    test('paginates results', function () {
        /** @var TestCase $this */
        Note::factory()->count(3)->create(['visible' => true]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(ListNotes::class, ['page' => 2, 'per_page' => 2]);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) {
            $json->where('total', 3)
                ->where('page', 2)
                ->where('per_page', 2)
                ->where('has_more_pages', false)
                ->has('notes', 1)
                ->etc();
        });
    });

    test('rejects a per_page above the maximum', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(ListNotes::class, ['per_page' => 251]);

        $response->assertHasErrors(['per page']);
    });

    test('rejects a page below one', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(ListNotes::class, ['page' => 0]);

        $response->assertHasErrors(['page']);
    });

    test('rejects a non-boolean include_drafts', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(ListNotes::class, ['include_drafts' => 'yes please']);

        $response->assertHasErrors(['include drafts']);
    });
});
