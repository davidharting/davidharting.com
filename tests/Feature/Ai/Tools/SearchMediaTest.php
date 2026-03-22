<?php

use App\Ai\Tools\SearchMedia;
use App\Models\Media;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

test('SearchMedia has a meaningful description', function () {
    /** @var TestCase $this */
    $tool = new SearchMedia;

    $description = $tool->description();

    $this->assertStringContainsStringIgnoringCase('media', $description);
    $this->assertStringContainsStringIgnoringCase('title', $description);
    $this->assertStringContainsStringIgnoringCase('status', $description);
});

test('SearchMedia schema defines title, media_type, and creator fields', function () {
    /** @var TestCase $this */
    $fields = (new SearchMedia)->schema(new JsonSchemaTypeFactory);

    $this->assertArrayHasKey('title', $fields);
    $this->assertArrayHasKey('media_type', $fields);
    $this->assertArrayHasKey('creator', $fields);
});

test('SearchMedia returns error when no search fields are provided', function () {
    /** @var TestCase $this */
    $result = json_decode((new SearchMedia)->handle(new Request([])), true);

    $this->assertArrayHasKey('error', $result);
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
