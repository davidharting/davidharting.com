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

    Media::factory()
        ->show()
        ->has(MediaEvent::factory()->started()->at(Carbon::parse('2025-01-01')), 'events')
        ->create(['title' => 'In Progress TV Show']);

    $result = (new InProgressQuery)->execute();
    $this->assertCount(2, $result);
    $this->assertEquals('In Progress TV Show', $result->first()->title);
    $this->assertEquals('In Progress Book', $result->last()->title);
});
