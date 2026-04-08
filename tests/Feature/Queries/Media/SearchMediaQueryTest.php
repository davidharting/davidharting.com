<?php

use App\Enum\MediaTypeName;
use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaEvent;
use App\Queries\Media\SearchMediaQuery;
use Illuminate\Foundation\Testing\TestCase;

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
