<?php

use App\Models\Media;
use App\Models\MediaEvent;
use App\Queries\Media\InProgressQuery;
use Illuminate\Support\Carbon;

test('example', function () {
    Media::factory()
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

    $result = (new InProgressQuery)->execute();
    $this->assertCount(1, $result);
    $this->assertEquals('In Progress Book', $result->first()->title);
});
