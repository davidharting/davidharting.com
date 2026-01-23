<?php

use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

test('empty state', function () {
    $response = $this->get('/media');

    $response->assertStatus(200);
    $response->assertSeeText('No items');
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
        $response = $this->get('/media?list=backlog');

        $response->assertStatus(200);
        $response->assertSeeTextInOrder([
            '2023 July 22',
            'Backlogged Album',
            'Artist One',
            '2022 January 01',
            'Backlogged Book',
            'Author One',
        ]);
        $response->assertDontSeeText('Watched Movie');
        $response->assertDontSeeText('Listened Album');
        $response->assertDontSeeText('Read Book');
        $response->assertDontSeeText('Reading Book');
    });

    test('Backlog as admin', function () {
        $this->actingAs(User::factory(['is_admin' => true])->create());

        $response = $this->get('/media?list=backlog');

        $response->assertStatus(200);
        $response->assertSeeTextInOrder([
            '2023 July 22',
            'Backlogged Album',
            'Artist One',
            '2022 January 01',
            'Backlogged Book',
            'Author One',
        ]);
    });

    test('In Progress', function () {
        $response = $this->get('/media?list=in-progress');

        $response->assertStatus(200);
        $response->assertSeeTextInOrder([
            '2024 December 29',
            'Reading Book',
            'Author Three',
        ]);
        $response->assertDontSeeText('Backlogged Album');
        $response->assertDontSeeText('Backlogged Book');
        $response->assertDontSeeText('Watched Movie');
        $response->assertDontSeeText('Listened Album');
        $response->assertDontSeeText('Read Book');
    });

    test('In Progress as admin', function () {
        $this->actingAs(User::factory(['is_admin' => true])->create());

        $response = $this->get('/media?list=in-progress');

        $response->assertStatus(200);
        $response->assertSeeTextInOrder([
            '2024 December 29',
            'Reading Book',
            'Author Three',
        ]);
    });

    test('Finished (default)', function () {
        $response = $this->get('/media');

        $response->assertStatus(200);
        $response->assertSeeTextInOrder([
            '2022 January 07',
            'Watched Movie',

            '2019 December 26',
            'Read Book',
            'Author Two',

            '2018 September 06',
            'Listened Album',
            'Artist Two',
        ]);
        $response->assertDontSeeText('Backlogged Album');
        $response->assertDontSeeText('Backlogged Book');
        $response->assertDontSeeText('Reading Book');
    });

    describe('admin edit link', function () {
        test('guest user cannot see it', function () {
            $response = $this->get('/media');

            $response->assertDontSeeText('Edit');
        });

        test('regular users cannot see it', function () {
            $this->actingAs(User::factory(['is_admin' => false])->create());

            $response = $this->get('/media');

            $response->assertDontSeeText('Edit');
        });

        test('admins can see it', function () {
            $this->actingAs(User::factory(['is_admin' => true])->create());

            $response = $this->get('/media');

            $response->assertSeeText('Edit');
        });
    });

    describe('clickable titles', function () {
        test('guest users see plain text titles', function () {
            $media = Media::where('title', 'Watched Movie')->first();

            $response = $this->get('/media');

            $response->assertSeeText('Watched Movie');
            $response->assertDontSee(route('media.show', $media->id));
        });

        test('regular users see plain text titles', function () {
            $this->actingAs(User::factory(['is_admin' => false])->create());
            $media = Media::where('title', 'Watched Movie')->first();

            $response = $this->get('/media');

            $response->assertSeeText('Watched Movie');
            $response->assertDontSee(route('media.show', $media->id));
        });

        test('admins see clickable titles linking to detail page', function () {
            $this->actingAs(User::factory(['is_admin' => true])->create());

            $media = Media::where('title', 'Watched Movie')->first();

            $response = $this->get('/media');

            $response->assertSeeText('Watched Movie');
            $response->assertSee(route('media.show', $media->id));
        });
    });

    describe('notes', function () {
        test('guest users cannot see note', function () {
            $response = $this->get('/media');

            $response->assertDontSeeText('Recommended by George');
            $response->assertDontSeeText('It was great!');
        });

        test('regular users cannot see note', function () {
            $this->actingAs(User::factory(['is_admin' => false])->create());

            $response = $this->get('/media');

            $response->assertDontSeeText('Recommended by George');
            $response->assertDontSeeText('It was great!');
        });

        test('admin users see note', function () {
            $this->actingAs(User::factory(['is_admin' => true])->create());

            $response = $this->get('/media');

            $response->assertSeeTextInOrder(['Recommended by George', 'It was great!']);
        });
    });
});
