<?php

use App\Enum\MediaTypeName;
use App\Models\MediaType;
use Tests\TestCase;

test('factory works', function () {
    $mediaType = MediaType::factory()->make();
    expect($mediaType)->toBeInstanceOf(MediaType::class);
    expect($mediaType->name)->not->toBeEmpty();
});

test('media type seeded with data', function () {
    /** @var TestCase $this */
    $mediaTypeNames = MediaType::orderBy('name', 'asc')->pluck('name')->toArray();
    $this->assertCount(5, $mediaTypeNames);

    foreach (MediaTypeName::cases() as $expectedName) {
        expect(in_array($expectedName, $mediaTypeNames))->toBeTrue();
    }
});
