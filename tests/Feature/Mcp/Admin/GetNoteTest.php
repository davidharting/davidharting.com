<?php

use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\GetNote;
use App\Models\Note;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Mcp\Request;
use Tests\TestCase;

describe('shouldRegister()', function () {
    // A real note, so "not found" can only mean the *tool* was not found. With
    // no note in the database both failures produce that phrase and the test
    // would pass without the gate doing anything.
    beforeEach(function () {
        Note::factory()->create(['slug' => 'published-note', 'visible' => true]);
    });

    test('an anonymous caller cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $response = AdminServer::tool(GetNote::class, ['slug' => 'published-note']);

        // "Tool not found" rather than "denied": tools/call resolves through
        // the same filtered collection tools/list does, so a tool the caller
        // cannot register simply is not there.
        $response->assertHasErrors(['Tool [get-note] not found']);
    });

    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);

        $response = AdminServer::actingAs($user)->tool(GetNote::class, ['slug' => 'published-note']);

        $response->assertHasErrors(['Tool [get-note] not found']);
    });

    test('an admin can reach the tool', function () {
        /** @var TestCase $this */
        $note = Note::factory()->create(['visible' => true]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(GetNote::class, ['slug' => $note->slug]);

        $response->assertOk();
    });
});

describe('handle()', function () {
    test('withholds a draft from a non-admin', function () {
        /** @var TestCase $this */
        // Called directly, since going through the server would only prove
        // shouldRegister works. The point is that handle() refuses on its own
        // too, so registering this tool somewhere it does not belong leaks
        // nothing.
        $note = Note::factory()->create(['title' => 'SECRET DRAFT', 'visible' => false]);
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new GetNote)->handle(new Request(['slug' => $note->slug]));

        expect($response->isError())->toBeTrue()
            ->and((string) $response->content())->toBe('Note not found.')
            ->and((string) $response->content())->not->toContain('SECRET DRAFT');
    });

    test('serves a published note to a non-admin', function () {
        /** @var TestCase $this */
        // The other half of NotePolicy::view: a published note is public, so
        // the data guard has no reason to withhold it. Keeping the guard
        // identical to the website's is the point — shouldRegister is what
        // keeps non-admins off this tool.
        $note = Note::factory()->create(['title' => 'A public note', 'visible' => true]);
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new GetNote)->handle(new Request(['slug' => $note->slug]));

        expect($response->isError())->toBeFalse()
            ->and((string) $response->content())->toContain('A public note');
    });

    test('returns a published note as markdown, labelled published', function () {
        /** @var TestCase $this */
        $note = Note::factory()->create([
            'title' => 'My Great Note',
            'lead' => 'An interesting lead',
            'markdown_content' => "Some **bold** content.\n\nA second paragraph.",
            'visible' => true,
            'published_at' => Carbon::create(2024, 6, 1),
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(GetNote::class, ['slug' => $note->slug]);

        $url = route('notes.show', $note->slug);

        $response->assertOk();
        $response->assertSee(<<<MARKDOWN
            # My Great Note

            *An interesting lead*

            Status: published

            Published: 2024-06-01

            URL: {$url}

            ---

            Some **bold** content.

            A second paragraph.
            MARKDOWN);
    });

    test('returns a draft, labelled as a draft', function () {
        /** @var TestCase $this */
        // No opt-in: a slug names one note, and the caller already named it.
        // The label is what keeps the draft from being read as published work.
        $note = Note::factory()->create([
            'title' => 'Work in progress',
            'markdown_content' => 'Half an argument.',
            'visible' => false,
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(GetNote::class, ['slug' => $note->slug]);

        $response->assertOk();
        $response->assertSee('# Work in progress');
        $response->assertSee('Half an argument.');
        $response->assertSee('Status: draft');
    });

    test('returns not found for a slug that does not exist', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(GetNote::class, ['slug' => 'does-not-exist']);

        $response->assertHasErrors(['Note not found.']);
    });

    test('requires a slug', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(GetNote::class);

        $response->assertHasErrors(['slug']);
    });
});
