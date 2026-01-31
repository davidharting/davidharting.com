<?php

use App\Models\Note;
use App\Queries\FeedQuery;

test('returns only visible notes', function () {
    Note::factory()->create(['visible' => true, 'title' => 'Visible']);
    Note::factory()->create(['visible' => false, 'title' => 'Hidden']);

    $results = FeedQuery::execute();

    expect($results)->toHaveCount(1);
    expect($results->first()->title)->toBe('Visible');
});

test('returns notes in reverse chronological order', function () {
    Note::factory()->create([
        'visible' => true,
        'title' => 'Oldest',
        'published_at' => now()->subDays(3),
    ]);
    Note::factory()->create([
        'visible' => true,
        'title' => 'Newest',
        'published_at' => now()->subDays(1),
    ]);
    Note::factory()->create([
        'visible' => true,
        'title' => 'Middle',
        'published_at' => now()->subDays(2),
    ]);

    $results = FeedQuery::execute();

    expect($results->pluck('title')->toArray())->toBe(['Newest', 'Middle', 'Oldest']);
});

test('limits to 50 notes', function () {
    Note::factory()->count(60)->create(['visible' => true]);

    $results = FeedQuery::execute();

    expect($results)->toHaveCount(50);
});
