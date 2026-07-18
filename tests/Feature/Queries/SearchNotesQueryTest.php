<?php

use App\Models\Note;
use App\Queries\SearchNotesQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase;

test('matches against title, lead, and markdown content', function () {
    /** @var TestCase $this */
    Note::factory()->create(['title' => 'All about the xylophone', 'visible' => true]);
    Note::factory()->create(['lead' => 'A xylophone story', 'visible' => true]);
    Note::factory()->create(['markdown_content' => 'I bought a xylophone yesterday.', 'visible' => true]);
    Note::factory()->create([
        'title' => 'Ordinary title',
        'lead' => 'Ordinary lead',
        'markdown_content' => 'Ordinary content',
        'visible' => true,
    ]);

    $notes = (new SearchNotesQuery('xylophone'))->execute();

    $this->assertCount(3, $notes);
});

test('is case-insensitive', function () {
    /** @var TestCase $this */
    Note::factory()->create([
        'title' => 'The XYLOPHONE Chronicles',
        'visible' => true,
    ]);

    $notes = (new SearchNotesQuery('xylophone'))->execute();

    $this->assertCount(1, $notes);
});

test('excludes invisible notes', function () {
    /** @var TestCase $this */
    Note::factory()->create([
        'title' => 'Secret xylophone draft',
        'visible' => false,
    ]);

    $notes = (new SearchNotesQuery('xylophone'))->execute();

    $this->assertEmpty($notes);
});

test('orders results with most recently published first', function () {
    /** @var TestCase $this */
    Note::factory()->createMany([
        ['title' => 'Old xylophone post', 'published_at' => Carbon::create(2020, 1, 1), 'visible' => true],
        ['title' => 'New xylophone post', 'published_at' => Carbon::create(2024, 1, 1), 'visible' => true],
    ]);

    $notes = (new SearchNotesQuery('xylophone'))->execute();

    $this->assertSame(
        ['New xylophone post', 'Old xylophone post'],
        $notes->pluck('title')->all(),
    );
});

test('paginates results', function () {
    /** @var TestCase $this */
    Note::factory()->createMany([
        ['title' => 'Xylophone post one', 'visible' => true],
        ['title' => 'Xylophone post two', 'visible' => true],
        ['title' => 'Xylophone post three', 'visible' => true],
    ]);

    $paginator = (new SearchNotesQuery('xylophone'))->paginate(perPage: 2, page: 2);

    $this->assertSame(3, $paginator->total());
    $this->assertCount(1, $paginator->items());
    $this->assertFalse($paginator->hasMorePages());
});

test('matches wildcard characters literally', function () {
    /** @var TestCase $this */
    Note::factory()->create([
        'title' => 'Working at 100% capacity',
        'visible' => true,
    ]);
    Note::factory()->create([
        'title' => 'Working at 100 miles per hour',
        'visible' => true,
    ]);

    $notes = (new SearchNotesQuery('100%'))->execute();

    $this->assertSame(['Working at 100% capacity'], $notes->pluck('title')->all());
});
