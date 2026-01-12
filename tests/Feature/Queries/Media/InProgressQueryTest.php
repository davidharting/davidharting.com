<?php

use App\Models\Media;
use App\Models\MediaEvent;
use App\Queries\Media\InProgressQuery;
use Illuminate\Support\Carbon;

test('example', function () {
    $inProgressBook = Media::factory()
        ->book()
        ->has(MediaEvent::factory()->started()->at(Carbon::parse('2024-12-29')), 'events')
        // Leave Creator ID null to validate left join from media to creators
        ->create(['title' => 'In Progress Book', 'creator_id' => null]);

    Media::factory()
        ->album()
        ->has(MediaEvent::factory()->started()->at(Carbon::parse('2024-11-1')), 'events')
        ->has(MediaEvent::factory()->finished()->at(Carbon::parse('2024-11-3')), 'events')
        ->create(['title' => 'Completed Album']);

    Media::factory()
        ->movie()
        ->has(MediaEvent::factory()->finished()->at(Carbon::parse('2024-10-1')), 'events')
        ->create(['title' => 'Completed Movie']);

    Media::factory()
        ->game()
        ->create(['title' => 'Backlogged Game']);

    $inProgressTvShow = Media::factory()
        ->show()
        ->has(MediaEvent::factory()->started()->at(Carbon::parse('2025-01-01')), 'events')
        ->create(['title' => 'In Progress TV Show']);

    $result = (new InProgressQuery)->execute();
    $this->assertCount(2, $result);

    $this->assertEquals('In Progress TV Show', $result->first()->title);
    $this->assertEquals($inProgressTvShow->id, $result->first()->id);

    $this->assertEquals('In Progress Book', $result->last()->title);
    $this->assertEquals($inProgressBook->id, $result->last()->id);
});

test('comment events do not affect in progress status', function () {
    // Media with started event AND comment events should still be in progress
    $inProgressWithComments = Media::factory()
        ->book()
        ->has(MediaEvent::factory()->started()->at(Carbon::parse('2024-12-01')), 'events')
        ->has(MediaEvent::factory()->comment('Loving chapter 3!')->at(Carbon::parse('2024-12-10')), 'events')
        ->create(['title' => 'Book With Comments']);

    // Media with ONLY comment events (no started) should NOT be in progress
    Media::factory()
        ->book()
        ->has(MediaEvent::factory()->comment('Just a note'), 'events')
        ->create(['title' => 'Only Comment Book']);

    $result = (new InProgressQuery)->execute();
    $this->assertCount(1, $result);
    $this->assertEquals('Book With Comments', $result->first()->title);
});
