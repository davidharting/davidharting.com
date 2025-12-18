<?php

use App\Enum\MediaEventTypeName;
use App\Models\MediaEventType;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\TestCase;

test('factory fails due to unique constraint on name', function () {
    /** @var TestCase $this */
    MediaEventType::factory()->create();
})->throws(UniqueConstraintViolationException::class);

test('idFor returns the correct ID for each event type', function () {
    /** @var TestCase $this */
    foreach (MediaEventTypeName::cases() as $name) {
        $expected = MediaEventType::where('name', $name)->first()->id;
        $actual = MediaEventType::idFor($name);
        $actualCached = MediaEventType::idFor($name);

        expect($actual)->toBe($expected);
        expect($actualCached)->toBe($expected);
    }
});

test('idFor caches results', function () {
    /** @var TestCase $this */
    // Call twice - second call should use cache
    $first = MediaEventType::idFor(MediaEventTypeName::STARTED);
    $second = MediaEventType::idFor(MediaEventTypeName::STARTED);

    expect($first)->toBe($second);

    // Different name should also work (not return cached value from first call)
    $comment = MediaEventType::idFor(MediaEventTypeName::COMMENT);
    expect($comment)->not->toBe($first);
    expect($comment)->toBe(MediaEventType::where('name', MediaEventTypeName::COMMENT)->first()->id);
});
