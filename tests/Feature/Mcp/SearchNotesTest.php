<?php

use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\SearchNotes;
use App\Models\Note;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase;

test('matches against title, lead, and markdown content', function (array $attributes) {
    /** @var TestCase $this */
    $note = Note::factory()->create([
        'title' => 'Ordinary title',
        'lead' => 'Ordinary lead',
        'markdown_content' => 'Ordinary content',
        'visible' => true,
        ...$attributes,
    ]);

    $response = PublicServer::tool(SearchNotes::class, ['query' => 'xylophone']);

    $response->assertOk();
    $response->assertStructuredContent(function ($json) use ($note) {
        $json->where('total', 1)
            ->where('notes.0.slug', $note->slug)
            ->etc();
    });
})->with([
    'title' => [['title' => 'All about the xylophone']],
    'lead' => [['lead' => 'A xylophone story']],
    'markdown content' => [['markdown_content' => 'I bought a xylophone yesterday.']],
]);

test('is case-insensitive', function () {
    /** @var TestCase $this */
    Note::factory()->create([
        'title' => 'The XYLOPHONE Chronicles',
        'visible' => true,
    ]);

    $response = PublicServer::tool(SearchNotes::class, ['query' => 'xylophone']);

    $response->assertStructuredContent(function ($json) {
        $json->where('total', 1)->etc();
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

test('orders results with most recently published first', function () {
    /** @var TestCase $this */
    Note::factory()->createMany([
        ['title' => 'Old xylophone post', 'published_at' => Carbon::create(2020, 1, 1), 'visible' => true],
        ['title' => 'New xylophone post', 'published_at' => Carbon::create(2024, 1, 1), 'visible' => true],
    ]);

    $response = PublicServer::tool(SearchNotes::class, ['query' => 'xylophone']);

    $response->assertStructuredContent(function ($json) {
        $json->where('notes.0.title', 'New xylophone post')
            ->where('notes.1.title', 'Old xylophone post')
            ->etc();
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

test('wildcard characters are matched literally', function () {
    /** @var TestCase $this */
    Note::factory()->create([
        'title' => 'Working at 100% capacity',
        'visible' => true,
    ]);
    Note::factory()->create([
        'title' => 'Working at 100 miles per hour',
        'visible' => true,
    ]);

    $response = PublicServer::tool(SearchNotes::class, ['query' => '100%']);

    $response->assertStructuredContent(function ($json) {
        $json->where('total', 1)
            ->where('notes.0.title', 'Working at 100% capacity')
            ->etc();
    });
});

test('requires a query', function () {
    /** @var TestCase $this */
    $response = PublicServer::tool(SearchNotes::class);

    $response->assertHasErrors(['query']);
});
