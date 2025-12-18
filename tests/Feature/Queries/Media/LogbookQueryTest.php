<?php

use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaEvent;
use App\Queries\Media\LogbookQuery;
use Tests\TestCase;

test('1 item', function () {
    /** @var TestCase $this */
    $media = Media::factory()
        ->book()
        ->for(Creator::factory(['name' => 'J.R.R. Tolkien']))
        ->has(MediaEvent::factory()->finished()->state(['occurred_at' => '2023-02-07', 'comment' => null]), 'events')
        ->create([
            'title' => 'The Hobbit',
            'note' => 'Classic!',
        ]);

    $result = (new LogbookQuery)->execute();
    expect($result)->toHaveCount(1);

    $first = $result->first();
    expect($first->id)->toBe($media->id);
    expect($first->title)->toBe('The Hobbit');
    expect($first->creator)->toBe('J.R.R. Tolkien');
    expect($first->type)->toBe('book');
    expect($first->note)->toBe('Classic!');
    $this->stringStartsWith($first->occurred_at, '2023-02-07');
});

test('returns finished comment column', function () {
    /** @var TestCase $this */
    $media = Media::factory()
        ->book()
        ->for(Creator::factory(['name' => 'Isaac Asimov']))
        ->has(MediaEvent::factory()->finished()->state(['occurred_at' => '2024-03-15', 'comment' => 'Mind-blowing sci-fi concepts!']), 'events')
        ->create([
            'title' => 'Foundation',
            'note' => 'Part of a famous series',
        ]);

    $result = (new LogbookQuery)->execute();
    expect($result)->toHaveCount(1);

    $first = $result->first();
    expect($first->id)->toBe($media->id);
    expect($first->title)->toBe('Foundation');
    expect($first->finished_comment)->toBe('Mind-blowing sci-fi concepts!');
});

test('comment events do not appear in logbook', function () {
    /** @var TestCase $this */
    // Media with finished event should appear in logbook
    $finishedMedia = Media::factory()
        ->book()
        ->has(MediaEvent::factory()->finished()->state(['occurred_at' => '2024-01-15']), 'events')
        ->create(['title' => 'Finished Book']);

    // Media with ONLY comment events should NOT appear in logbook
    Media::factory()
        ->book()
        ->has(MediaEvent::factory()->comment('Some notes'), 'events')
        ->create(['title' => 'Only Comment Book']);

    // Media with started + comment events should NOT appear in logbook
    Media::factory()
        ->book()
        ->has(MediaEvent::factory()->started()->state(['occurred_at' => '2024-01-01']), 'events')
        ->has(MediaEvent::factory()->comment('Halfway through'), 'events')
        ->create(['title' => 'In Progress With Comments']);

    $result = (new LogbookQuery)->execute();
    expect($result)->toHaveCount(1);
    expect($result->first()->title)->toBe('Finished Book');
});
