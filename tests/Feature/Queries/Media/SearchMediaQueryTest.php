<?php

use App\Enum\MediaSort;
use App\Enum\MediaTrackingStatus;
use App\Enum\MediaTypeName;
use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaEvent;
use App\Queries\Media\SearchMediaQuery;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Carbon;

test('returns empty collection when no media matches', function () {
    /** @var TestCase $this */
    $results = (new SearchMediaQuery(title: 'Nonexistent Title XYZ'))->execute();

    $this->assertEmpty($results);
});

test('result includes all expected fields', function () {
    /** @var TestCase $this */
    Media::factory()->book()->create(['title' => 'Dune', 'year' => 1965]);

    $item = (new SearchMediaQuery(title: 'Dune'))->execute()->sole();

    $this->assertNotNull($item->media_id);
    $this->assertSame('Dune', $item->title);
    $this->assertSame(1965, (int) $item->year);
    $this->assertSame('book', $item->media_type);
    $this->assertNotNull($item->creator);
    $this->assertSame('backlog', $item->current_status);
    $this->assertNull($item->started_at);
    $this->assertNull($item->finished_at);
    $this->assertNull($item->abandoned_at);
});

describe('title search', function () {
    test('finds a media item by partial title match', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()->create(['title' => 'The Hobbit']);

        $results = (new SearchMediaQuery(title: 'hobbit'))->execute();

        $this->assertCount(1, $results);
        $this->assertSame($media->id, $results->sole()->media_id);
        $this->assertSame('The Hobbit', $results->sole()->title);
    });

    test('is case-insensitive', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Dune']);

        $results = (new SearchMediaQuery(title: 'DUNE'))->execute();

        $this->assertCount(1, $results);
    });

    test('returns all matching items when multiple media share a partial title', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Dune']);
        Media::factory()->book()->create(['title' => 'Dune Messiah']);
        Media::factory()->book()->create(['title' => 'Children of Dune']);

        $results = (new SearchMediaQuery(title: 'dune'))->execute();

        $this->assertCount(3, $results);
    });

    test('wildcard characters are matched literally', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => '100% Unofficial Guide']);
        // "100 Things" contains "100" and would match if '%' were treated as a wildcard
        Media::factory()->book()->create(['title' => '100 Things']);

        $results = (new SearchMediaQuery(title: '100%'))->execute();

        $this->assertCount(1, $results);
        $this->assertSame('100% Unofficial Guide', $results->sole()->title);
    });
});

describe('creator search', function () {
    test('filters by partial creator match', function () {
        /** @var TestCase $this */
        $creator = Creator::factory()->create(['name' => 'Frank Herbert']);
        Media::factory()->book()->create(['title' => 'Dune', 'creator_id' => $creator->id]);
        Media::factory()->book()->create(['title' => 'Other Book']);

        $results = (new SearchMediaQuery(creator: 'Herbert'))->execute();

        $this->assertCount(1, $results);
        $this->assertSame('Dune', $results->sole()->title);
    });

    test('is case-insensitive', function () {
        /** @var TestCase $this */
        $creator = Creator::factory()->create(['name' => 'Frank Herbert']);
        Media::factory()->book()->create(['title' => 'Dune', 'creator_id' => $creator->id]);
        Media::factory()->book()->create(['title' => 'Other Book']);

        $results = (new SearchMediaQuery(creator: 'FRANK'))->execute();

        $this->assertCount(1, $results);
    });

    test('excludes non-matching creator', function () {
        /** @var TestCase $this */
        $creator = Creator::factory()->create(['name' => 'Frank Herbert']);
        Media::factory()->book()->create(['title' => 'Dune', 'creator_id' => $creator->id]);

        $results = (new SearchMediaQuery(creator: 'Tolkien'))->execute();

        $this->assertEmpty($results);
    });
});

describe('media type filter', function () {
    test('filters by media type', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Dune']);
        Media::factory()->movie()->create(['title' => 'Dune']);

        $results = (new SearchMediaQuery(title: 'Dune', mediaType: MediaTypeName::Book))->execute();

        $this->assertCount(1, $results);
        $this->assertSame('book', $results->sole()->media_type);
    });

    test('returns empty collection when no items match the type', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Dune']);
        Media::factory()->movie()->create(['title' => 'Dune']);

        $results = (new SearchMediaQuery(title: 'Dune', mediaType: MediaTypeName::Album))->execute();

        $this->assertEmpty($results);
    });
});

describe('filter combinations', function () {
    test('title and creator both must match', function () {
        /** @var TestCase $this */
        $herbert = Creator::factory()->create(['name' => 'Frank Herbert']);
        $tolkien = Creator::factory()->create(['name' => 'J.R.R. Tolkien']);
        Media::factory()->book()->create(['title' => 'Dune', 'creator_id' => $herbert->id]);
        Media::factory()->book()->create(['title' => 'Dune (fan retelling)', 'creator_id' => $tolkien->id]);

        $results = (new SearchMediaQuery(title: 'Dune', creator: 'Herbert'))->execute();

        $this->assertCount(1, $results);
        $this->assertSame('Dune', $results->sole()->title);
    });

    test('title and media type both must match', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Dune']);
        Media::factory()->movie()->create(['title' => 'Dune']);

        $results = (new SearchMediaQuery(title: 'Dune', mediaType: MediaTypeName::Movie))->execute();

        $this->assertCount(1, $results);
        $this->assertSame('movie', $results->sole()->media_type);
    });

    test('creator and media type both must match', function () {
        /** @var TestCase $this */
        $herbert = Creator::factory()->create(['name' => 'Frank Herbert']);
        Media::factory()->book()->create(['title' => 'Dune', 'creator_id' => $herbert->id]);
        Media::factory()->movie()->create(['title' => 'Dune', 'creator_id' => $herbert->id]);

        $results = (new SearchMediaQuery(creator: 'Herbert', mediaType: MediaTypeName::Book))->execute();

        $this->assertCount(1, $results);
        $this->assertSame('book', $results->sole()->media_type);
    });

    test('title, creator, and media type all must match', function () {
        /** @var TestCase $this */
        $herbert = Creator::factory()->create(['name' => 'Frank Herbert']);
        $tolkien = Creator::factory()->create(['name' => 'J.R.R. Tolkien']);
        Media::factory()->book()->create(['title' => 'Dune', 'creator_id' => $herbert->id]);
        Media::factory()->movie()->create(['title' => 'Dune', 'creator_id' => $herbert->id]);
        Media::factory()->book()->create(['title' => 'Dune (unofficial)', 'creator_id' => $tolkien->id]);

        $results = (new SearchMediaQuery(title: 'Dune', creator: 'Herbert', mediaType: MediaTypeName::Book))->execute();

        $this->assertCount(1, $results);
        $this->assertSame('Dune', $results->sole()->title);
        $this->assertSame('book', $results->sole()->media_type);
    });
});

describe('status', function () {
    test('reports backlog status when media has no events', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Foundation']);

        $item = (new SearchMediaQuery(title: 'Foundation'))->execute()->sole();

        $this->assertSame('backlog', $item->current_status);
    });

    test('reports started status when last non-comment event is started', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()->create(['title' => 'Neuromancer']);
        MediaEvent::factory()->started()->create(['media_id' => $media->id]);

        $item = (new SearchMediaQuery(title: 'Neuromancer'))->execute()->sole();

        $this->assertSame('started', $item->current_status);
        $this->assertNotNull($item->started_at);
    });

    test('reports finished status when last non-comment event is finished', function () {
        /** @var TestCase $this */
        Media::factory()->book()
            ->has(MediaEvent::factory()->started()->at(now()->subDays(10)), 'events')
            ->has(MediaEvent::factory()->comment('Distractor event')->at(now()->subDays(8)), 'events')
            ->has(MediaEvent::factory()->finished()->at(now()), 'events')
            ->create(['title' => 'Neuromancer']);

        $item = (new SearchMediaQuery(title: 'Neuromancer'))->execute()->sole();

        $this->assertSame('finished', $item->current_status);
        $this->assertNotNull($item->finished_at);
    });

    test('reports abandoned status when last non-comment event is abandoned', function () {
        /** @var TestCase $this */
        Media::factory()->book()
            ->has(MediaEvent::factory()->started()->at(now()->subDays(10)), 'events')
            ->has(MediaEvent::factory()->abandoned()->at(now()), 'events')
            ->create(['title' => 'Neuromancer']);

        $item = (new SearchMediaQuery(title: 'Neuromancer'))->execute()->sole();

        $this->assertSame('abandoned', $item->current_status);
        $this->assertNotNull($item->abandoned_at);
    });

    test('comment events do not affect current status', function () {
        /** @var TestCase $this */
        Media::factory()->book()
            ->has(MediaEvent::factory()->started()->at(now()->subDays(10)), 'events')
            ->has(MediaEvent::factory()->comment('Good so far')->at(now()), 'events')
            ->create(['title' => 'Neuromancer']);

        $item = (new SearchMediaQuery(title: 'Neuromancer'))->execute()->sole();

        $this->assertSame('started', $item->current_status);
    });
});

describe('status filter', function () {
    test('filters by backlog status', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Untouched Book']);
        Media::factory()->book()
            ->has(MediaEvent::factory()->started(), 'events')
            ->create(['title' => 'Started Book']);

        $results = (new SearchMediaQuery(status: MediaTrackingStatus::Backlog))->execute();

        $this->assertCount(1, $results);
        $this->assertSame('Untouched Book', $results->sole()->title);
    });

    test('filters by started status', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Untouched Book']);
        Media::factory()->book()
            ->has(MediaEvent::factory()->started(), 'events')
            ->create(['title' => 'Started Book']);

        $results = (new SearchMediaQuery(status: MediaTrackingStatus::Started))->execute();

        $this->assertCount(1, $results);
        $this->assertSame('Started Book', $results->sole()->title);
    });

    test('filters by finished status', function () {
        /** @var TestCase $this */
        Media::factory()->book()
            ->has(MediaEvent::factory()->started()->at(now()->subDays(2)), 'events')
            ->has(MediaEvent::factory()->finished()->at(now()), 'events')
            ->create(['title' => 'Finished Book']);
        Media::factory()->book()
            ->has(MediaEvent::factory()->started()->at(now()->subDays(2)), 'events')
            ->has(MediaEvent::factory()->abandoned()->at(now()), 'events')
            ->create(['title' => 'Abandoned Book']);

        $results = (new SearchMediaQuery(status: MediaTrackingStatus::Finished))->execute();

        $this->assertCount(1, $results);
        $this->assertSame('Finished Book', $results->sole()->title);
    });

    test('filters by abandoned status', function () {
        /** @var TestCase $this */
        Media::factory()->book()
            ->has(MediaEvent::factory()->started()->at(now()->subDays(2)), 'events')
            ->has(MediaEvent::factory()->finished()->at(now()), 'events')
            ->create(['title' => 'Finished Book']);
        Media::factory()->book()
            ->has(MediaEvent::factory()->started()->at(now()->subDays(2)), 'events')
            ->has(MediaEvent::factory()->abandoned()->at(now()), 'events')
            ->create(['title' => 'Abandoned Book']);

        $results = (new SearchMediaQuery(status: MediaTrackingStatus::Abandoned))->execute();

        $this->assertCount(1, $results);
        $this->assertSame('Abandoned Book', $results->sole()->title);
    });
});

describe('year filters', function () {
    test('filters by release year', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Old Book', 'year' => 1965]);
        Media::factory()->book()->create(['title' => 'New Book', 'year' => 2020]);

        $results = (new SearchMediaQuery(year: 1965))->execute();

        $this->assertCount(1, $results);
        $this->assertSame('Old Book', $results->sole()->title);
    });

    test('filters by started year', function () {
        /** @var TestCase $this */
        Media::factory()->book()
            ->has(MediaEvent::factory()->started()->at(Carbon::create(2023, 5, 1)), 'events')
            ->create(['title' => 'Started In 2023']);
        Media::factory()->book()
            ->has(MediaEvent::factory()->started()->at(Carbon::create(2024, 5, 1)), 'events')
            ->create(['title' => 'Started In 2024']);

        $results = (new SearchMediaQuery(startedYear: 2023))->execute();

        $this->assertCount(1, $results);
        $this->assertSame('Started In 2023', $results->sole()->title);
    });

    test('filters by finished year', function () {
        /** @var TestCase $this */
        Media::factory()->book()
            ->has(MediaEvent::factory()->finished()->at(Carbon::create(2023, 5, 1)), 'events')
            ->create(['title' => 'Finished In 2023']);
        Media::factory()->book()
            ->has(MediaEvent::factory()->finished()->at(Carbon::create(2024, 5, 1)), 'events')
            ->create(['title' => 'Finished In 2024']);

        $results = (new SearchMediaQuery(finishedYear: 2023))->execute();

        $this->assertCount(1, $results);
        $this->assertSame('Finished In 2023', $results->sole()->title);
    });

    test('release year is distinct from finished year', function () {
        /** @var TestCase $this */
        // A 1965 book finished in 2024: matches year=1965 and finishedYear=2024,
        // but not year=2024.
        Media::factory()->book()
            ->has(MediaEvent::factory()->finished()->at(Carbon::create(2024, 5, 1)), 'events')
            ->create(['title' => 'Dune', 'year' => 1965]);

        $this->assertCount(1, (new SearchMediaQuery(year: 1965))->execute());
        $this->assertCount(1, (new SearchMediaQuery(finishedYear: 2024))->execute());
        $this->assertEmpty((new SearchMediaQuery(year: 2024))->execute());
    });

    test('items without events are excluded by event-year filters', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Backlog Book']);

        $this->assertEmpty((new SearchMediaQuery(startedYear: 2024))->execute());
        $this->assertEmpty((new SearchMediaQuery(finishedYear: 2024))->execute());
    });
});

describe('sort', function () {
    test('recently_finished puts most recently finished first and unfinished last', function () {
        /** @var TestCase $this */
        Media::factory()->book()
            ->has(MediaEvent::factory()->finished()->at(Carbon::create(2023, 1, 1)), 'events')
            ->create(['title' => 'Finished Earlier']);
        Media::factory()->book()->create(['title' => 'Never Finished']);
        Media::factory()->book()
            ->has(MediaEvent::factory()->finished()->at(Carbon::create(2024, 1, 1)), 'events')
            ->create(['title' => 'Finished Recently']);

        $titles = (new SearchMediaQuery(sort: MediaSort::RecentlyFinished))->execute()->pluck('title')->all();

        $this->assertSame(['Finished Recently', 'Finished Earlier', 'Never Finished'], $titles);
    });

    test('recently_started puts most recently started first and unstarted last', function () {
        /** @var TestCase $this */
        Media::factory()->book()
            ->has(MediaEvent::factory()->started()->at(Carbon::create(2023, 1, 1)), 'events')
            ->create(['title' => 'Started Earlier']);
        Media::factory()->book()->create(['title' => 'Never Started']);
        Media::factory()->book()
            ->has(MediaEvent::factory()->started()->at(Carbon::create(2024, 1, 1)), 'events')
            ->create(['title' => 'Started Recently']);

        $titles = (new SearchMediaQuery(sort: MediaSort::RecentlyStarted))->execute()->pluck('title')->all();

        $this->assertSame(['Started Recently', 'Started Earlier', 'Never Started'], $titles);
    });

    test('recently_added puts the newest library entries first', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Added First']);
        Media::factory()->book()->create(['title' => 'Added Second']);

        $titles = (new SearchMediaQuery(sort: MediaSort::RecentlyAdded))->execute()->pluck('title')->all();

        $this->assertSame(['Added Second', 'Added First'], $titles);
    });

    test('title sorts alphabetically', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Zebra']);
        Media::factory()->book()->create(['title' => 'Aardvark']);

        $titles = (new SearchMediaQuery(sort: MediaSort::Title))->execute()->pluck('title')->all();

        $this->assertSame(['Aardvark', 'Zebra'], $titles);
    });

    test('year puts the most recent releases first', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Old Release', 'year' => 1965]);
        Media::factory()->book()->create(['title' => 'New Release', 'year' => 2020]);

        $titles = (new SearchMediaQuery(sort: MediaSort::Year))->execute()->pluck('title')->all();

        $this->assertSame(['New Release', 'Old Release'], $titles);
    });
});

describe('paginate()', function () {
    test('returns the requested page with the total count', function () {
        /** @var TestCase $this */
        Media::factory()->book()->count(3)->create();

        $paginator = (new SearchMediaQuery(sort: MediaSort::RecentlyAdded))->paginate(perPage: 2, page: 2);

        $this->assertSame(3, $paginator->total());
        $this->assertSame(2, $paginator->currentPage());
        $this->assertCount(1, $paginator->items());
    });

    test('applies filters', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Dune']);
        Media::factory()->book()->create(['title' => 'Foundation']);

        $paginator = (new SearchMediaQuery(title: 'Dune'))->paginate(perPage: 10, page: 1);

        $this->assertSame(1, $paginator->total());
    });

    test('pages never overlap when sorted', function () {
        /** @var TestCase $this */
        // Identical titles force the sort to fall through to the tiebreaker.
        Media::factory()->book()->count(4)->create(['title' => 'Same Title']);

        $query = new SearchMediaQuery(sort: MediaSort::Title);
        $firstPage = collect($query->paginate(perPage: 2, page: 1)->items())->pluck('media_id');
        $secondPage = collect($query->paginate(perPage: 2, page: 2)->items())->pluck('media_id');

        $this->assertCount(4, $firstPage->merge($secondPage)->unique());
    });
});
