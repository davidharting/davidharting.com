<?php

use App\Ai\Tools\SearchMedia;
use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaEvent;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Carbon;
use Laravel\Ai\Tools\Request;

test('SearchMedia has a meaningful description', function () {
    /** @var TestCase $this */
    $tool = new SearchMedia;

    $description = $tool->description();

    $this->assertStringContainsStringIgnoringCase('media', $description);
    $this->assertStringContainsStringIgnoringCase('title', $description);
    $this->assertStringContainsStringIgnoringCase('status', $description);
});

test('SearchMedia schema defines all filter, sort, and pagination fields', function () {
    /** @var TestCase $this */
    $fields = (new SearchMedia)->schema(new JsonSchemaTypeFactory);

    foreach (['title', 'media_type', 'creator', 'status', 'year', 'started_year', 'finished_year', 'sort', 'page', 'limit'] as $field) {
        $this->assertArrayHasKey($field, $fields);
    }
});

test('SearchMedia schema enumerates valid media_type values', function () {
    /** @var TestCase $this */
    $schema = (new SearchMedia)->schema(new JsonSchemaTypeFactory);
    $compiled = $schema['media_type']->toArray();

    $this->assertEqualsCanonicalizing(
        ['album', 'book', 'movie', 'tv show', 'video game'],
        $compiled['enum'],
    );
});

test('SearchMedia returns error when an invalid media_type is provided', function () {
    /** @var TestCase $this */
    $result = json_decode(
        (new SearchMedia)->handle(new Request(['title' => 'Dune', 'media_type' => 'podcast'])),
        true,
    );

    $this->assertArrayHasKey('error', $result);
    $this->assertStringContainsString('podcast', $result['error']);
});

test('SearchMedia browses the whole library when no filters are provided', function () {
    /** @var TestCase $this */
    Media::factory()->book()->create(['title' => 'Dune']);
    Media::factory()->movie()->create(['title' => 'Alien']);

    $result = json_decode((new SearchMedia)->handle(new Request([])), true);

    $this->assertTrue($result['found']);
    $this->assertSame(2, $result['total']);
});

test('SearchMedia filters by status', function () {
    /** @var TestCase $this */
    Media::factory()->book()
        ->has(MediaEvent::factory()->finished(), 'events')
        ->create(['title' => 'Finished Book']);
    Media::factory()->book()->create(['title' => 'Backlog Book']);

    $result = json_decode((new SearchMedia)->handle(new Request(['status' => 'finished'])), true);

    $this->assertSame(1, $result['total']);
    $this->assertSame('Finished Book', $result['results'][0]['title']);
});

test('SearchMedia returns error when an invalid status is provided', function () {
    /** @var TestCase $this */
    $result = json_decode((new SearchMedia)->handle(new Request(['status' => 'paused'])), true);

    $this->assertArrayHasKey('error', $result);
    $this->assertStringContainsString('paused', $result['error']);
});

test('SearchMedia filters by finished_year', function () {
    /** @var TestCase $this */
    Media::factory()->book()
        ->has(MediaEvent::factory()->finished()->at(Carbon::create(2024, 3, 1)), 'events')
        ->create(['title' => 'Finished in 2024']);
    Media::factory()->book()
        ->has(MediaEvent::factory()->finished()->at(Carbon::create(2023, 3, 1)), 'events')
        ->create(['title' => 'Finished in 2023']);

    $result = json_decode((new SearchMedia)->handle(new Request(['finished_year' => 2024])), true);

    $this->assertSame(1, $result['total']);
    $this->assertSame('Finished in 2024', $result['results'][0]['title']);
});

test('SearchMedia applies sort', function () {
    /** @var TestCase $this */
    Media::factory()->book()->create(['title' => 'Zebra']);
    Media::factory()->book()->create(['title' => 'Aardvark']);

    $result = json_decode((new SearchMedia)->handle(new Request(['sort' => 'title'])), true);

    $this->assertSame(['Aardvark', 'Zebra'], array_column($result['results'], 'title'));
});

test('SearchMedia paginates with a capped limit', function () {
    /** @var TestCase $this */
    Media::factory()->book()->count(3)->create();

    $result = json_decode((new SearchMedia)->handle(new Request(['limit' => 2, 'page' => 2])), true);

    $this->assertSame(3, $result['total']);
    $this->assertSame(2, $result['page']);
    $this->assertCount(1, $result['results']);
    $this->assertFalse($result['has_more_pages']);
});

test('SearchMedia returns JSON with found=false when no results', function () {
    /** @var TestCase $this */
    $result = json_decode((new SearchMedia)->handle(new Request(['title' => 'Nonexistent XYZ'])), true);

    $this->assertFalse($result['found']);
    $this->assertEmpty($result['results']);
});

test('SearchMedia returns JSON with found=true and results when media matches', function () {
    /** @var TestCase $this */
    $media = Media::factory()->book()->create(['title' => 'The Hobbit']);

    $result = json_decode((new SearchMedia)->handle(new Request(['title' => 'hobbit'])), true);

    $this->assertTrue($result['found']);
    $this->assertCount(1, $result['results']);
    $this->assertSame($media->id, $result['results'][0]['media_id']);
    $this->assertSame('The Hobbit', $result['results'][0]['title']);
});

test('SearchMedia results include creator_id', function () {
    /** @var TestCase $this */
    $creator = Creator::factory()->create(['name' => 'Frank Herbert']);
    Media::factory()->book()->create(['title' => 'Dune', 'creator_id' => $creator->id]);

    $result = json_decode((new SearchMedia)->handle(new Request(['title' => 'Dune'])), true);

    $this->assertTrue($result['found']);
    $this->assertArrayHasKey('creator_id', $result['results'][0]);
    $this->assertSame($creator->id, $result['results'][0]['creator_id']);
});

test('SearchMedia results include null creator_id when creator is not set', function () {
    /** @var TestCase $this */
    Media::factory()->book()->create(['title' => 'Unknown Origin', 'creator_id' => null]);

    $result = json_decode((new SearchMedia)->handle(new Request(['title' => 'Unknown Origin'])), true);

    $this->assertTrue($result['found']);
    $this->assertArrayHasKey('creator_id', $result['results'][0]);
    $this->assertNull($result['results'][0]['creator_id']);
});

test('SearchMedia media_type filter is case-insensitive', function () {
    /** @var TestCase $this */
    Media::factory()->book()->create(['title' => 'Dune']);
    Media::factory()->movie()->create(['title' => 'Dune']);

    $result = json_decode((new SearchMedia)->handle(new Request(['title' => 'Dune', 'media_type' => 'BOOK'])), true);

    $this->assertTrue($result['found']);
    $this->assertCount(1, $result['results']);
    $this->assertSame('book', $result['results'][0]['media_type']);
});
