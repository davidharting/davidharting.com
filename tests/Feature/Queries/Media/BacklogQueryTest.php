<?php

use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaEvent;
use App\Queries\Media\BacklogQuery;
use Carbon\Carbon;
use Tests\TestCase;

test('backlog query test', function () {
    /** @var TestCase $this */
    // Create distractor media with different events
    Media::factory()
        ->movie()
        ->has(MediaEvent::factory()->finished()->state(['occurred_at' => '2023-01-01']), 'events')
        ->create(['title' => 'Finished Media']);

    Media::factory()
        ->book()
        ->for(Creator::factory(['name' => 'Author 2']))
        ->has(MediaEvent::factory()->started()->state(['occurred_at' => '2023-01-02']), 'events')
        ->create(['title' => 'Started Media']);

    Media::factory()
        ->book()
        ->for(Creator::factory(['name' => 'Author 3']))
        ->has(MediaEvent::factory()->abandoned()->state(['occurred_at' => '2023-01-03']), 'events')
        ->create(['title' => 'Abandoned Media']);

    // Create additional media with no events
    $backlogBook = Media::factory(['created_at' => Carbon::parse('2023-01-08')])
        ->book()
        ->for(Creator::factory(['name' => 'Author 4']))
        ->create(['title' => 'Backlog Book']);

    $backlogMovie = Media::factory(['created_at' => Carbon::parse('2023-01-05')])
        ->movie()
        ->create(['title' => 'Backlog Movie']);

    $result = (new BacklogQuery)->execute();

    expect($result)->toHaveCount(2);

    expect($result->first()->id)->toBe($backlogBook->id);
    expect($result->first()->title)->toBe('Backlog Book');
    expect($result->first()->creator)->toBe('Author 4');
    expect($result->first()->type)->toBe('book');
    expect(Carbon::parse($result->first()->occurred_at)->format('Y F d'))->toBe('2023 January 08');

    expect($result->last()->title)->toBe('Backlog Movie');
    expect($result->last()->id)->toBe($backlogMovie->id);
});

test('backlog query ignores comment events', function () {
    /** @var TestCase $this */
    // Media with only comment events should still be in backlog
    $mediaWithCommentOnly = Media::factory(['created_at' => Carbon::parse('2023-01-10')])
        ->book()
        ->for(Creator::factory(['name' => 'Author With Comments']))
        ->has(MediaEvent::factory()->comment('Great so far!'), 'events')
        ->create(['title' => 'Book With Comment Only']);

    // Media with started event should NOT be in backlog
    Media::factory()
        ->book()
        ->has(MediaEvent::factory()->started(), 'events')
        ->create(['title' => 'Started Book']);

    // Media with no events should be in backlog
    $mediaNoEvents = Media::factory(['created_at' => Carbon::parse('2023-01-05')])
        ->book()
        ->create(['title' => 'Book No Events']);

    $result = (new BacklogQuery)->execute();

    expect($result)->toHaveCount(2);
    expect($result->pluck('title')->toArray())->toContain('Book With Comment Only');
    expect($result->pluck('title')->toArray())->toContain('Book No Events');
    expect($result->pluck('title')->toArray())->not->toContain('Started Book');
});
