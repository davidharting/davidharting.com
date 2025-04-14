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
        ->has(MediaEvent::factory()->finished()->state(['occurred_at' => '2023-02-07']), 'events')
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
