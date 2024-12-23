<?php

use App\Enum\MediaTypeName;
use App\Models\MediaType;
use Tests\TestCase;

test('factory works', function () {
    /** @var TestCase $this */
    $mediaType = MediaType::factory()->make();
    $this->assertNotNull($mediaType);
    $this->assertNotEmpty($mediaType->name);
});

test('media type seeded with data', function () {
    /** @var TestCase $this */
    $mediaTypeNames = MediaType::orderBy('name', 'asc')->pluck('name')->toArray();
    $this->assertCount(5, $mediaTypeNames);

    foreach (MediaTypeName::cases() as $expectedName) {
        expect(in_array($expectedName, $mediaTypeNames))->toBeTrue();
    }
});
