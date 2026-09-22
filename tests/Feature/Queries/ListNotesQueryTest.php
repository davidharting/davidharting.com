<?php

use App\Models\Note;
use App\Queries\ListNotesQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase;

test('returns notes most recently published first', function () {
    /** @var TestCase $this */
    Note::factory()->createMany([
        ['title' => 'Oldest note', 'published_at' => Carbon::create(2020, 1, 1), 'visible' => true],
        ['title' => 'Newest note', 'published_at' => Carbon::create(2024, 6, 1), 'visible' => true],
        ['title' => 'Middle note', 'published_at' => Carbon::create(2022, 3, 1), 'visible' => true],
    ]);

    $notes = (new ListNotesQuery)->execute();

    expect($notes->pluck('title')->all())->toBe(['Newest note', 'Middle note', 'Oldest note']);
});

test('excludes drafts by default', function () {
    /** @var TestCase $this */
    Note::factory()->create(['title' => 'Published note', 'visible' => true]);
    Note::factory()->create(['title' => 'Draft note', 'visible' => false]);

    $notes = (new ListNotesQuery)->execute();

    expect($notes->pluck('title')->all())->toBe(['Published note']);
});

test('includes drafts when asked', function () {
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

    $notes = (new ListNotesQuery(includeDrafts: true))->execute();

    expect($notes->pluck('title')->all())->toBe(['Draft note', 'Published note']);
});

test('paginates', function () {
    /** @var TestCase $this */
    Note::factory()->count(3)->create(['visible' => true]);

    $paginator = (new ListNotesQuery)->paginate(perPage: 2, page: 2);

    expect($paginator->total())->toBe(3)
        ->and($paginator->currentPage())->toBe(2)
        ->and($paginator->items())->toHaveCount(1)
        ->and($paginator->hasMorePages())->toBeFalse();
});

test('counts drafts in the total only when they are included', function () {
    /** @var TestCase $this */
    Note::factory()->count(2)->create(['visible' => true]);
    Note::factory()->create(['visible' => false]);

    expect((new ListNotesQuery)->paginate(perPage: 10, page: 1)->total())->toBe(2)
        ->and((new ListNotesQuery(includeDrafts: true))->paginate(perPage: 10, page: 1)->total())->toBe(3);
});
