<?php

use App\Livewire\Media\MediaPage;
use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaEvent;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

test('empty state', function () {
    Livewire::test(MediaPage::class)
        ->assertStatus(200)
        ->assertSee('No items');
});

describe('with data', function () {
    beforeEach(function () {
        // Backlog
        Media::factory()
            ->book()
            ->for(Creator::factory(['name' => 'Author One']))
            ->create(['title' => 'Backlogged Book', 'created_at' => Carbon::parse('2022-01-01')]);

        Media::factory()
            ->album()
            ->for(Creator::factory(['name' => 'Artist One']))
            ->create(['title' => 'Backlogged Album', 'created_at' => Carbon::parse('2023-07-22')]);

        // Activity
        Media::factory()
            ->movie()
            ->has(MediaEvent::factory()->started()->at(Carbon::parse('2022-01-06')), 'events')
            ->has(MediaEvent::factory()->finished()->at(Carbon::parse('2022-01-07')), 'events')
            ->create(['title' => 'Watched Movie']);

        Media::factory()
            ->book()
            ->for(Creator::factory(['name' => 'Author Two']))
            ->has(MediaEvent::factory()->started()->at(Carbon::parse('2019-10-15')), 'events')
            ->has(MediaEvent::factory()->finished()->at(Carbon::parse('2019-12-26')), 'events')
            ->create(['title' => 'Read Book']);

        Media::factory()
            ->album()
            ->for(Creator::factory(['name' => 'Artist Two']))
            ->has(MediaEvent::factory()->finished()->at(Carbon::parse('2018-9-6')), 'events')
            ->create(['title' => 'Listened Album']);

        // In Progress
        Media::factory()
            ->book()
            ->for(Creator::factory(['name' => 'Author Three']))
            ->has(MediaEvent::factory()->started()->at(Carbon::parse('2024-12-29')), 'events')
            ->create(['title' => 'Reading Book']);
    });

    test('Backlog', function () {
        Livewire::withQueryParams(['list' => 'backlog'])->test(MediaPage::class)
            ->assertStatus(200)
            ->assertSeeInOrder([
                '2023 July 22',
                'Backlogged Album',
                'Artist One',
                '2022 January 01',
                'Backlogged Book',
                'Author One',
            ])
            ->assertDontSee('Watched Movie')
            ->assertDontSee('Listened Album')
            ->assertDontSee('Read Book')
            ->assertDontSee('Reading Book');
    });

    test('In Progress', function () {
        Livewire::withQueryParams(['list' => 'in-progress'])->test(MediaPage::class)
            ->assertStatus(200)
            ->assertSeeInOrder([
                '2024 December 29',
                'Reading Book',
                'Author Three',
            ])
            ->assertDontSee('Backlogged Album')
            ->assertDontSee('Backlogged Book')
            ->assertDontSee('Watched Movie')
            ->assertDontSee('Listened Album')
            ->assertDontSee('Read Book');
    });

    test('Finished (default)', function () {
        Livewire::test(MediaPage::class)
            ->assertStatus(200)
            ->assertSeeInOrder([
                '2022 January 07',
                'Watched Movie',

                '2019 December 26',
                'Read Book',
                'Author Two',

                '2018 September 06',
                'Listened Album',
                'Artist Two',
            ])
            ->assertDontSee('Backlogged Album')
            ->assertDontSee('Backlogged Book')
            ->assertDontSee('Reading Book');
    });

    // TODO: Test filtering
});
