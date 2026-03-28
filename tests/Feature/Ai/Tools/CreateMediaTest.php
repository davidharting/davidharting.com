<?php

use App\Ai\Tools\CreateMedia;
use App\Models\Creator;
use App\Models\Media;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

describe('handle()', function () {
    test('creates media with a new creator by name', function () {
        /** @var TestCase $this */
        $result = json_decode(
            (new CreateMedia)->handle(new Request([
                'title' => 'Dune',
                'year' => 1965,
                'creator_name' => 'Frank Herbert',
                'media_type' => 'book',
            ])),
            true,
        );

        $this->assertTrue($result['media_created']);
        $this->assertTrue($result['creator_created']);
        $this->assertSame('Dune', $result['title']);
        $this->assertSame(1965, $result['year']);
        $this->assertSame('Frank Herbert', $result['creator']);
        $this->assertSame('book', $result['media_type']);
        $this->assertDatabaseHas('creators', ['name' => 'Frank Herbert']);
        $this->assertDatabaseHas('media', ['title' => 'Dune']);
    });

    test('creates media with an existing creator by id', function () {
        /** @var TestCase $this */
        $creator = Creator::factory()->create(['name' => 'Frank Herbert']);

        $result = json_decode(
            (new CreateMedia)->handle(new Request([
                'title' => 'Dune Messiah',
                'year' => 1969,
                'creator_id' => $creator->id,
                'media_type' => 'book',
            ])),
            true,
        );

        $this->assertTrue($result['media_created']);
        $this->assertFalse($result['creator_created']);
        $this->assertSame('Dune Messiah', $result['title']);
        $this->assertSame('Frank Herbert', $result['creator']);
        $this->assertDatabaseCount('creators', 1);
    });

    test('returns media_created=false when media already exists (firstOrCreate)', function () {
        /** @var TestCase $this */
        $creator = Creator::factory()->create(['name' => 'Frank Herbert']);
        Media::factory()->book()->create(['title' => 'Dune', 'creator_id' => $creator->id]);

        $result = json_decode(
            (new CreateMedia)->handle(new Request([
                'title' => 'Dune',
                'creator_id' => $creator->id,
                'media_type' => 'book',
            ])),
            true,
        );

        $this->assertFalse($result['media_created']);
        $this->assertDatabaseCount('media', 1);
    });

    test('returns creator_created=false when creator already exists and found by name', function () {
        /** @var TestCase $this */
        Creator::factory()->create(['name' => 'Frank Herbert']);

        $result = json_decode(
            (new CreateMedia)->handle(new Request([
                'title' => 'Children of Dune',
                'creator_name' => 'Frank Herbert',
                'media_type' => 'book',
            ])),
            true,
        );

        $this->assertTrue($result['media_created']);
        $this->assertFalse($result['creator_created']);
        $this->assertDatabaseCount('creators', 1);
    });

    test('returns error JSON when neither creator_id nor creator_name provided', function () {
        /** @var TestCase $this */
        $result = json_decode(
            (new CreateMedia)->handle(new Request([
                'title' => 'Dune',
                'media_type' => 'book',
            ])),
            true,
        );

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsStringIgnoringCase('creator', $result['error']);
    });

    test('returns error JSON when both creator_id and creator_name provided', function () {
        /** @var TestCase $this */
        $creator = Creator::factory()->create();

        $result = json_decode(
            (new CreateMedia)->handle(new Request([
                'title' => 'Dune',
                'media_type' => 'book',
                'creator_id' => $creator->id,
                'creator_name' => 'Frank Herbert',
            ])),
            true,
        );

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsStringIgnoringCase('creator', $result['error']);
    });

    test('stores note when provided', function () {
        /** @var TestCase $this */
        $result = json_decode(
            (new CreateMedia)->handle(new Request([
                'title' => 'Dune',
                'creator_name' => 'Frank Herbert',
                'media_type' => 'book',
                'note' => 'A classic sci-fi epic.',
            ])),
            true,
        );

        $this->assertTrue($result['media_created']);
        $this->assertDatabaseHas('media', ['title' => 'Dune', 'note' => 'A classic sci-fi epic.']);
    });
});

describe('schema()', function () {
    test('defines required fields', function () {
        /** @var TestCase $this */
        $fields = (new CreateMedia)->schema(new JsonSchemaTypeFactory);

        $this->assertArrayHasKey('title', $fields);
        $this->assertArrayHasKey('media_type', $fields);
        $this->assertArrayHasKey('creator_id', $fields);
        $this->assertArrayHasKey('creator_name', $fields);
    });

    test('enumerates valid media_type values', function () {
        /** @var TestCase $this */
        $schema = (new CreateMedia)->schema(new JsonSchemaTypeFactory);
        $compiled = $schema['media_type']->toArray();

        $this->assertEqualsCanonicalizing(
            ['album', 'book', 'movie', 'tv show', 'video game'],
            $compiled['enum'],
        );
    });
});
