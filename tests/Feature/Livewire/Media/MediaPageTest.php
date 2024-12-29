<?php

use App\Livewire\Media\MediaPage;
use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaEvent;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

test('empty state', function () {
    Livewire::test(MediaPage::class)
        ->assertStatus(200)
        ->assertSee('No items');
});

test('logbook', function () {
    /** @var TestCase $this */

    // Create backlog
    Media::factory()
        ->book()
        ->for(Creator::factory(['name' => 'Author One']))
        ->create(['title' => 'Backlogged Book']);

    Media::factory()
        ->album()
        ->for(Creator::factory(['name' => 'Artist One']))
        ->create(['title' => 'Backlogged Album']);

    // Create logbook
    Media::factory()
        ->movie()
        ->has(MediaEvent::factory()->started()->at(Carbon::parse('2022-01-06')), 'events')
        ->has(MediaEvent::factory()->finished()->at(Carbon::parse('2022-01-07')), 'events')
        ->create(['title' => 'Watched Movie']);

    Media::factory()
        ->book()
        ->for(Creator::factory(['name' => 'Author Two']))
        ->has(MediaEvent::factory()->started()->at(Carbon::parse('2019-10-15')), 'events')
        ->has(MediaEvent::factory()->finished()->at(Carbon::parse('2019-12-26')), 'events');

    Media::factory()
        ->album()
        ->for(Creator::factory(['name' => 'Artist Two']))
        ->has(MediaEvent::factory()->finished()->at(Carbon::parse('2018-9-6')), 'events')
        ->create(['title' => 'Listened Album']);

    // In Progress
    Media::factory()
        ->book()
        ->for(Creator::factory(['name' => 'Author Three']))
        ->has(MediaEvent::factory()->started()->at(Carbon::parse('2024-12-29')), 'events');

    Livewire::test(MediaPage::class)
        ->assertStatus(200);
});
