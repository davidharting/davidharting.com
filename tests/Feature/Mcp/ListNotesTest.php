<?php

use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\ListNotes;
use App\Models\Note;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase;

test('returns visible notes with most recently published first', function () {
    /** @var TestCase $this */
    Note::factory()->createMany([
        ['title' => 'Oldest note', 'published_at' => Carbon::create(2020, 1, 1), 'visible' => true],
        ['title' => 'Newest note', 'published_at' => Carbon::create(2024, 6, 1), 'visible' => true],
        ['title' => 'Middle note', 'published_at' => Carbon::create(2022, 3, 1), 'visible' => true],
    ]);

    $response = PublicServer::tool(ListNotes::class);

    $response->assertOk();
    $response->assertStructuredContent(function ($json) {
        $json->where('total', 3)
            ->where('per_page', 250)
            ->where('notes.0.title', 'Newest note')
            ->where('notes.1.title', 'Middle note')
            ->where('notes.2.title', 'Oldest note')
            ->etc();
    });
});

test('includes slug, title, lead, published_at, and url for each note', function () {
    /** @var TestCase $this */
    $note = Note::factory()->create([
        'title' => 'My Note',
        'lead' => 'A lead paragraph',
        'visible' => true,
        'published_at' => Carbon::create(2024, 6, 1, 12),
    ]);

    $response = PublicServer::tool(ListNotes::class);

    $response->assertOk();
    $response->assertStructuredContent(function ($json) use ($note) {
        $json->where('notes.0.slug', $note->slug)
            ->where('notes.0.title', 'My Note')
            ->where('notes.0.lead', 'A lead paragraph')
            ->where('notes.0.published_at', $note->published_at->toIso8601String())
            ->where('notes.0.url', route('notes.show', $note->slug))
            ->etc();
    });
});

test('never returns invisible notes', function () {
    /** @var TestCase $this */
    Note::factory()->create(['title' => 'Public note', 'visible' => true]);
    Note::factory()->create(['title' => 'SECRET DRAFT', 'visible' => false]);

    $response = PublicServer::tool(ListNotes::class);

    $response->assertOk();
    $response->assertDontSee('SECRET DRAFT');
    $response->assertStructuredContent(function ($json) {
        $json->where('total', 1)->etc();
    });
});

test('excludes markdown content from list responses', function () {
    /** @var TestCase $this */
    Note::factory()->create([
        'visible' => true,
        'markdown_content' => 'UNIQUE-BODY-CONTENT-MARKER',
    ]);

    $response = PublicServer::tool(ListNotes::class);

    $response->assertOk();
    $response->assertDontSee('UNIQUE-BODY-CONTENT-MARKER');
});

test('paginates results', function () {
    /** @var TestCase $this */
    Note::factory()->count(3)->create(['visible' => true]);

    $response = PublicServer::tool(ListNotes::class, ['page' => 2, 'per_page' => 2]);

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
    $response = PublicServer::tool(ListNotes::class, ['per_page' => 251]);

    $response->assertHasErrors(['per page']);
});

test('rejects a page below one', function () {
    /** @var TestCase $this */
    $response = PublicServer::tool(ListNotes::class, ['page' => 0]);

    $response->assertHasErrors(['page']);
});
