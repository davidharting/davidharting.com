<?php

use App\Models\Media;
use App\Models\MediaEvent;
use App\Queries\Media\SearchMediaQuery;
use Illuminate\Foundation\Testing\TestCase;

test('returns empty collection when no media matches', function () {
    /** @var TestCase $this */
    $results = (new SearchMediaQuery(title: 'Nonexistent Title XYZ'))->execute();

    $this->assertEmpty($results);
});

test('finds a media item by partial title match', function () {
    /** @var TestCase $this */
    $media = Media::factory()->book()->create(['title' => 'The Hobbit']);

    $results = (new SearchMediaQuery(title: 'hobbit'))->execute();

    $this->assertCount(1, $results);
    $this->assertSame($media->id, $results->first()->media_id);
    $this->assertSame('The Hobbit', $results->first()->title);
});

test('search is case-insensitive', function () {
    /** @var TestCase $this */
    Media::factory()->book()->create(['title' => 'Dune']);

    $results = (new SearchMediaQuery(title: 'DUNE'))->execute();

    $this->assertCount(1, $results);
});

test('result includes all expected fields', function () {
    /** @var TestCase $this */
    Media::factory()->book()->create(['title' => 'Dune', 'year' => 1965]);

    $item = (new SearchMediaQuery(title: 'Dune'))->execute()->first();

    $this->assertNotNull($item->media_id);
    $this->assertSame('Dune', $item->title);
    $this->assertSame(1965, (int) $item->year);
    $this->assertSame('book', $item->media_type);
    $this->assertNotNull($item->creator);
    $this->assertSame('book', $item->media_type);
    $this->assertSame('backlog', $item->current_status);
    $this->assertNull($item->started_at);
    $this->assertNull($item->finished_at);
    $this->assertNull($item->abandoned_at);
});

test('reports backlog status when media has no events', function () {
    /** @var TestCase $this */
    Media::factory()->book()->create(['title' => 'Foundation']);

    $item = (new SearchMediaQuery(title: 'Foundation'))->execute()->first();

    $this->assertSame('backlog', $item->current_status);
});

test('reports started status when last non-comment event is started', function () {
    /** @var TestCase $this */
    $media = Media::factory()->book()->create(['title' => 'Neuromancer']);
    MediaEvent::factory()->started()->create(['media_id' => $media->id]);

    $item = (new SearchMediaQuery(title: 'Neuromancer'))->execute()->first();

    $this->assertSame('started', $item->current_status);
    $this->assertNotNull($item->started_at);
});

test('reports finished status when last non-comment event is finished', function () {
    /** @var TestCase $this */
    $media = Media::factory()->book()->create(['title' => 'Neuromancer']);
    MediaEvent::factory()->started()->at(now()->subDays(10))->create(['media_id' => $media->id]);
    MediaEvent::factory()->finished()->at(now())->create(['media_id' => $media->id]);

    $item = (new SearchMediaQuery(title: 'Neuromancer'))->execute()->first();

    $this->assertSame('finished', $item->current_status);
    $this->assertNotNull($item->finished_at);
});

test('reports abandoned status when last non-comment event is abandoned', function () {
    /** @var TestCase $this */
    $media = Media::factory()->book()->create(['title' => 'Neuromancer']);
    MediaEvent::factory()->started()->at(now()->subDays(10))->create(['media_id' => $media->id]);
    MediaEvent::factory()->abandoned()->at(now())->create(['media_id' => $media->id]);

    $item = (new SearchMediaQuery(title: 'Neuromancer'))->execute()->first();

    $this->assertSame('abandoned', $item->current_status);
    $this->assertNotNull($item->abandoned_at);
});

test('comment events do not affect current status', function () {
    /** @var TestCase $this */
    $media = Media::factory()->book()->create(['title' => 'Neuromancer']);
    MediaEvent::factory()->started()->at(now()->subDays(10))->create(['media_id' => $media->id]);
    MediaEvent::factory()->comment('Good so far')->at(now())->create(['media_id' => $media->id]);

    $item = (new SearchMediaQuery(title: 'Neuromancer'))->execute()->first();

    $this->assertSame('started', $item->current_status);
});

test('filters by media type', function () {
    /** @var TestCase $this */
    Media::factory()->book()->create(['title' => 'Dune']);
    Media::factory()->movie()->create(['title' => 'Dune']);

    $results = (new SearchMediaQuery(title: 'Dune', mediaType: 'book'))->execute();

    $this->assertCount(1, $results);
    $this->assertSame('book', $results->first()->media_type);
});

test('media type filter is case-insensitive', function () {
    /** @var TestCase $this */
    Media::factory()->book()->create(['title' => 'Dune']);

    $results = (new SearchMediaQuery(title: 'Dune', mediaType: 'BOOK'))->execute();

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

test('media type filter returns empty collection when no items match the type', function () {
    /** @var TestCase $this */
    Media::factory()->book()->create(['title' => 'Dune']);
    Media::factory()->movie()->create(['title' => 'Dune']);

    $results = (new SearchMediaQuery(title: 'Dune', mediaType: 'album'))->execute();

    $this->assertEmpty($results);
});

test('titles containing LIKE wildcard characters are matched literally', function () {
    /** @var TestCase $this */
    Media::factory()->book()->create(['title' => '100% Unofficial Guide']);
    Media::factory()->book()->create(['title' => 'Something Else']);

    $results = (new SearchMediaQuery(title: '100%'))->execute();

    $this->assertCount(1, $results);
    $this->assertSame('100% Unofficial Guide', $results->first()->title);
});
