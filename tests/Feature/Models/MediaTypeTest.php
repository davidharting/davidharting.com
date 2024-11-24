<?php

use App\Models\MediaType;
use Tests\TestCase;

test('factory works', function () {
    /** @var TestCase $this */
    $mediaType = MediaType::factory()->create();
    $this->assertNotNull($mediaType);
    $this->assertNotEmpty($mediaType->name);
});

test('media type seeded with data', function () {
    /** @var TestCase $this */
    $mediaTypes = MediaType::all('name')->toArray();
    $this->assertCount(4, $mediaTypes);

    $mediaTypeNames = array_column($mediaTypes, 'name');

    $expectedNames = ['tv show', 'book', 'movie', 'album'];
    foreach ($expectedNames as $expectedName) {
        expect(in_array($expectedName, $mediaTypeNames))->toBeTrue();
    }
});
