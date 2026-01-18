<?php

use App\Livewire\Media\MediaPage;
use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaEvent;
use App\Models\User;
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
            ->has(MediaEvent::factory()->state(['comment' => 'It was great!'])->finished()->at(Carbon::parse('2019-12-26')), 'events')
            ->create(['title' => 'Read Book', 'note' => 'Recommended by George']);

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

    test('Backlog as admin', function () {
        $this->actingAs(User::factory(['is_admin' => true])->create());

        Livewire::withQueryParams(['list' => 'backlog'])->test(MediaPage::class)
            ->assertStatus(200)
            ->assertSeeInOrder([
                '2023 July 22',
                'Backlogged Album',
                'Artist One',
                '2022 January 01',
                'Backlogged Book',
                'Author One',
            ]);
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

    test('In Progress as admin', function () {
        $this->actingAs(User::factory(['is_admin' => true])->create());

        Livewire::withQueryParams(['list' => 'in-progress'])->test(MediaPage::class)
            ->assertStatus(200)
            ->assertSeeInOrder([
                '2024 December 29',
                'Reading Book',
                'Author Three',
            ]);
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

    describe('admin edit link', function () {
        test('guest user cannot set it', function () {
            Livewire::test(MediaPage::class)
                ->assertDontSee('Edit');
        });

        test('regular users cannot set it', function () {
            $this->actingAs(User::factory(['is_admin' => false])->create());

            Livewire::test(MediaPage::class)
                ->assertDontSee('Edit');
        });

        test('admins can see it', function () {
            $this->actingAs(User::factory(['is_admin' => true])->create());

            Livewire::test(MediaPage::class)
                ->assertSee('Edit');
        });
    });

    describe('clickable titles', function () {
        test('guest users see plain text titles', function () {
            $mediaId = Media::first()->id;

            Livewire::test(MediaPage::class)
                ->assertDontSee(route('media.show', $mediaId));
        });

        test('regular users see plain text titles', function () {
            $this->actingAs(User::factory(['is_admin' => false])->create());
            $mediaId = Media::first()->id;

            Livewire::test(MediaPage::class)
                ->assertDontSee(route('media.show', $mediaId));
        });

        test('admins see clickable titles linking to detail page', function () {
            $this->actingAs(User::factory(['is_admin' => true])->create());

            $media = Media::where('title', 'Watched Movie')->first();
            Livewire::test(MediaPage::class)
                ->assertSee(route('media.show', $media->id));
        });
    });

    describe('notes', function () {
        test('guest users cannot see note', function () {
            Livewire::test(MediaPage::class)
                ->assertDontSee('Recommended by George')
                ->assertDontSee('It was great!');
        });

        test('regular users cannot see note', function () {
            $this->actingAs(User::factory(['is_admin' => false])->create());

            Livewire::test(MediaPage::class)
                ->assertDontSee('Recommended by George')
                ->assertDontSee('It was great!');
        });

        test('admin users see note', function () {
            $this->actingAs(User::factory(['is_admin' => true])->create());

            Livewire::test(MediaPage::class)
                ->assertSeeInOrder(['Recommended by George', 'It was great!']);
        });
    });

    // TODO: Test filtering
});
