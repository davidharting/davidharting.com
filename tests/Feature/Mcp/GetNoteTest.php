<?php

use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\GetNote;
use App\Models\Note;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase;

test('returns a visible note as markdown', function () {
    /** @var TestCase $this */
    $note = Note::factory()->create([
        'title' => 'My Great Note',
        'lead' => 'An interesting lead',
        'markdown_content' => "Some **bold** content.\n\nA second paragraph.",
        'visible' => true,
        'published_at' => Carbon::create(2024, 6, 1),
    ]);

    $response = PublicServer::tool(GetNote::class, ['slug' => $note->slug]);

    $url = route('notes.show', $note->slug);

    $response->assertOk();
    $response->assertSee(<<<MARKDOWN
        # My Great Note

        *An interesting lead*

        Published: 2024-06-01

        URL: {$url}

        ---

        Some **bold** content.

        A second paragraph.
        MARKDOWN);
});

test('renders a note without a title', function () {
    /** @var TestCase $this */
    $note = Note::factory()->leadOnly()->create([
        'lead' => 'Only a lead here',
        'visible' => true,
    ]);

    $response = PublicServer::tool(GetNote::class, ['slug' => $note->slug]);

    $response->assertOk();
    $response->assertSee('Only a lead here');
});

test('returns an error for a missing slug', function () {
    /** @var TestCase $this */
    $response = PublicServer::tool(GetNote::class, ['slug' => 'does-not-exist']);

    $response->assertHasErrors(['Note not found.']);
});

test('returns the identical error for an invisible note, never confirming it exists', function () {
    /** @var TestCase $this */
    $note = Note::factory()->create([
        'title' => 'Hidden draft',
        'visible' => false,
    ]);

    $response = PublicServer::tool(GetNote::class, ['slug' => $note->slug]);

    $response->assertHasErrors(['Note not found.']);
    $response->assertDontSee('Hidden draft');
});

test('requires a slug', function () {
    /** @var TestCase $this */
    $response = PublicServer::tool(GetNote::class);

    $response->assertHasErrors(['slug']);
});
