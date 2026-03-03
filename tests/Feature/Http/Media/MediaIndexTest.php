<?php

use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

test('empty state', function () {
    /** @var TestCase $this */
    $this->get('/media')
        ->assertStatus(200)
        ->assertSeeText('No items');
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
        /** @var TestCase $this */
        $this->get('/media?list=backlog')
            ->assertStatus(200)
            ->assertSeeTextInOrder([
                '2023',
                'July',
                '22',
                'Backlogged Album',
                'Artist One',
                '2022',
                'January',
                '1',
                'Backlogged Book',
                'Author One',
            ])
            ->assertDontSeeText('Watched Movie')
            ->assertDontSeeText('Listened Album')
            ->assertDontSeeText('Read Book')
            ->assertDontSeeText('Reading Book');
    });

    test('Backlog as admin', function () {
        /** @var TestCase $this */
        $this->actingAs(User::factory(['is_admin' => true])->create());

        $this->get('/media?list=backlog')
            ->assertStatus(200)
            ->assertSeeTextInOrder([
                '2023',
                'July',
                '22',
                'Backlogged Album',
                'Artist One',
                '2022',
                'January',
                '1',
                'Backlogged Book',
                'Author One',
            ]);
    });

    test('In Progress', function () {
        /** @var TestCase $this */
        $this->get('/media?list=in-progress')
            ->assertStatus(200)
            ->assertSeeTextInOrder([
                '2024',
                'December',
                '29',
                'Reading Book',
                'Author Three',
            ])
            ->assertDontSeeText('Backlogged Album')
            ->assertDontSeeText('Backlogged Book')
            ->assertDontSeeText('Watched Movie')
            ->assertDontSeeText('Listened Album')
            ->assertDontSeeText('Read Book');
    });

    test('In Progress as admin', function () {
        /** @var TestCase $this */
        $this->actingAs(User::factory(['is_admin' => true])->create());

        $this->get('/media?list=in-progress')
            ->assertStatus(200)
            ->assertSeeTextInOrder([
                '2024',
                'December',
                '29',
                'Reading Book',
                'Author Three',
            ]);
    });

    test('Finished (default)', function () {
        /** @var TestCase $this */
        $this->get('/media')
            ->assertStatus(200)
            ->assertSeeTextInOrder([
                '2022',
                'January',
                '7',
                'Watched Movie',

                '2019',
                'December',
                '26',
                'Read Book',
                'Author Two',

                '2018',
                'September',
                '6',
                'Listened Album',
                'Artist Two',
            ])
            ->assertDontSeeText('Backlogged Album')
            ->assertDontSeeText('Backlogged Book')
            ->assertDontSeeText('Reading Book');
    });

    describe('admin edit link', function () {
        test('guest user cannot see it', function () {
            /** @var TestCase $this */
            $this->get('/media')
                ->assertDontSeeText('Edit');
        });

        test('regular users cannot see it', function () {
            /** @var TestCase $this */
            $this->actingAs(User::factory(['is_admin' => false])->create());

            $this->get('/media')
                ->assertDontSeeText('Edit');
        });

        test('admins can see it', function () {
            /** @var TestCase $this */
            $this->actingAs(User::factory(['is_admin' => true])->create());

            $this->get('/media')
                ->assertSeeText('Edit');
        });
    });

    describe('clickable titles', function () {
        test('guest users see plain text titles', function () {
            /** @var TestCase $this */
            $media = Media::where('title', 'Watched Movie')->first();

            $this->get('/media')
                ->assertSeeText('Watched Movie')
                ->assertDontSee(route('media.show', $media->id));
        });

        test('regular users see plain text titles', function () {
            /** @var TestCase $this */
            $this->actingAs(User::factory(['is_admin' => false])->create());
            $media = Media::where('title', 'Watched Movie')->first();

            $this->get('/media')
                ->assertSeeText('Watched Movie')
                ->assertDontSee(route('media.show', $media->id));
        });

        test('admins see clickable titles linking to detail page', function () {
            /** @var TestCase $this */
            $this->actingAs(User::factory(['is_admin' => true])->create());

            $media = Media::where('title', 'Watched Movie')->first();
            $this->get('/media')
                ->assertSeeText('Watched Movie')
                ->assertSee(route('media.show', $media->id));
        });
    });

    describe('notes', function () {
        test('guest users cannot see note', function () {
            /** @var TestCase $this */
            $this->get('/media')
                ->assertDontSeeText('Recommended by George')
                ->assertDontSeeText('It was great!');
        });

        test('regular users cannot see note', function () {
            /** @var TestCase $this */
            $this->actingAs(User::factory(['is_admin' => false])->create());

            $this->get('/media')
                ->assertDontSeeText('Recommended by George')
                ->assertDontSeeText('It was great!');
        });

        test('admin users see note', function () {
            /** @var TestCase $this */
            $this->actingAs(User::factory(['is_admin' => true])->create());

            $this->get('/media')
                ->assertSeeTextInOrder(['Recommended by George', 'It was great!']);
        });
    });

    test('displays year and month headings for finished items', function () {
        /** @var TestCase $this */
        // Clear existing data
        MediaEvent::query()->delete();
        Media::query()->delete();

        // Create items spanning multiple years and months
        Media::factory()
            ->movie()
            ->has(MediaEvent::factory()->finished()->at(Carbon::create(2024, 3, 15)), 'events')
            ->create(['title' => 'March Movie']);

        Media::factory()
            ->book()
            ->has(MediaEvent::factory()->finished()->at(Carbon::create(2024, 3, 8)), 'events')
            ->create(['title' => 'March Book']);

        Media::factory()
            ->album()
            ->has(MediaEvent::factory()->finished()->at(Carbon::create(2024, 1, 20)), 'events')
            ->create(['title' => 'January Album']);

        Media::factory()
            ->movie()
            ->has(MediaEvent::factory()->finished()->at(Carbon::create(2022, 12, 25)), 'events')
            ->create(['title' => 'December Movie']);

        $response = $this->get('/media');
        $response->assertSuccessful();

        // Verify year and month headings appear in correct order with items
        $response->assertSeeTextInOrder([
            '2024',      // Year heading
            'March',     // Month heading
            '15',        // Day
            'March Movie',
            '8',         // Day
            'March Book',
            'January',   // Month heading (same year, no year repeat)
            '20',        // Day
            'January Album',
            '2022',      // Year heading
            'December',  // Month heading
            '25',        // Day
            'December Movie',
        ]);

        // Verify gap year is not present
        $response->assertDontSeeText('2023');

        // Verify months without items are not present
        $response->assertDontSeeText('February');
        $response->assertDontSeeText('November');
    });

    // TODO: Test filtering

    test('invalid list parameter redirects to clean URL', function () {
        /** @var TestCase $this */
        $this->get('/media?list=invalid-value')
            ->assertRedirect('/media');
    });

    test('invalid list parameter preserves other valid filters in redirect', function () {
        /** @var TestCase $this */
        $this->get('/media?list=invalid-value&year=2022&type=book')
            ->assertRedirect('/media?year=2022&type=book');
    });
});
