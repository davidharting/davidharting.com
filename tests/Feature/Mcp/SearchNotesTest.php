<?php

use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\SearchNotes;
use App\Models\Note;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase;

test('returns matching notes with their metadata', function () {
    /** @var TestCase $this */
    $note = Note::factory()->create([
        'title' => 'All about the xylophone',
        'lead' => 'A percussion story',
        'visible' => true,
        'published_at' => Carbon::create(2024, 6, 1, 12),
    ]);

    $response = PublicServer::tool(SearchNotes::class, ['query' => 'xylophone']);

    $response->assertOk();
    $response->assertStructuredContent(function ($json) use ($note) {
        $json->where('total', 1)
            ->where('per_page', 250)
            ->where('notes.0.slug', $note->slug)
            ->where('notes.0.title', 'All about the xylophone')
            ->where('notes.0.lead', 'A percussion story')
            ->where('notes.0.published_at', $note->published_at->toIso8601String())
            ->where('notes.0.url', route('notes.show', $note->slug))
            ->etc();
    });
});

test('never returns invisible notes', function () {
    /** @var TestCase $this */
    Note::factory()->create([
        'title' => 'Secret xylophone draft',
        'visible' => false,
    ]);

    $response = PublicServer::tool(SearchNotes::class, ['query' => 'xylophone']);

    $response->assertOk();
    $response->assertDontSee('Secret xylophone draft');
    $response->assertStructuredContent(function ($json) {
        $json->where('total', 0)->etc();
    });
});

test('includes a snippet of the surrounding content when the match is in the body', function () {
    /** @var TestCase $this */
    Note::factory()->create([
        'markdown_content' => 'The beginning of the note. Then I mention the xylophone in passing. And a long ending follows.',
        'visible' => true,
    ]);

    $response = PublicServer::tool(SearchNotes::class, ['query' => 'xylophone']);

    $response->assertStructuredContent(function ($json) {
        $json->whereType('notes.0.snippet', 'string')->etc();
    });
    $response->assertSee('I mention the xylophone in passing');
});

test('snippet is null when the match is only in the title', function () {
    /** @var TestCase $this */
    Note::factory()->create([
        'title' => 'A xylophone story',
        'markdown_content' => 'The body never mentions the instrument.',
        'visible' => true,
    ]);

    $response = PublicServer::tool(SearchNotes::class, ['query' => 'xylophone']);

    $response->assertStructuredContent(function ($json) {
        $json->where('notes.0.snippet', null)->etc();
    });
});

test('paginates results', function () {
    /** @var TestCase $this */
    Note::factory()->createMany([
        ['title' => 'Xylophone post one', 'visible' => true],
        ['title' => 'Xylophone post two', 'visible' => true],
        ['title' => 'Xylophone post three', 'visible' => true],
    ]);

    $response = PublicServer::tool(SearchNotes::class, ['query' => 'xylophone', 'page' => 2, 'per_page' => 2]);

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
    $response = PublicServer::tool(SearchNotes::class, ['query' => 'xylophone', 'per_page' => 251]);

    $response->assertHasErrors(['per page']);
});

test('rejects a query shorter than the minimum length', function () {
    /** @var TestCase $this */
    $response = PublicServer::tool(SearchNotes::class, ['query' => 'xyl']);

    $response->assertHasErrors(['query']);
});

test('requires a query', function () {
    /** @var TestCase $this */
    $response = PublicServer::tool(SearchNotes::class);

    $response->assertHasErrors(['query']);
});
