<?php

use App\Memories\PhotoVariant;
use App\Models\Memory;
use App\Models\MemoryPhoto;
use Tests\TestCase;

/**
 * A ready photo with known variants, so the model's reading of them is tested
 * without leaning on the factory's sizes.
 */
function readyMemoryPhoto(): MemoryPhoto
{
    return MemoryPhoto::factory()->create([
        'web_path' => 'memories/photos/abc/web.jpg',
        'web_mime_type' => 'image/jpeg',
        'web_width' => 1536,
        'web_height' => 2048,
        'web_bytes' => 412_345,
        'thumbnail_path' => 'memories/photos/abc/thumbnail.jpg',
        'thumbnail_mime_type' => 'image/webp',
        'thumbnail_width' => 384,
        'thumbnail_height' => 512,
        'thumbnail_bytes' => 31_234,
    ])->fresh();
}

describe('isProcessing()', function () {
    test('is true until its variants are made or processing gives up', function () {
        /** @var TestCase $this */
        expect(MemoryPhoto::factory()->create()->isProcessing())->toBeTrue()
            ->and(MemoryPhoto::factory()->ready()->create()->fresh()->isProcessing())->toBeFalse()
            ->and(MemoryPhoto::factory()->failed()->create()->fresh()->isProcessing())->toBeFalse();
    });
});

describe('isReady()', function () {
    test('is true only once its variants are stored', function () {
        /** @var TestCase $this */
        expect(MemoryPhoto::factory()->ready()->create()->fresh()->isReady())->toBeTrue()
            ->and(MemoryPhoto::factory()->create()->isReady())->toBeFalse()
            ->and(MemoryPhoto::factory()->failed()->create()->fresh()->isReady())->toBeFalse();
    });
});

describe('isFailed()', function () {
    test('is true only once processing gives up', function () {
        /** @var TestCase $this */
        expect(MemoryPhoto::factory()->failed()->create()->fresh()->isFailed())->toBeTrue()
            ->and(MemoryPhoto::factory()->create()->isFailed())->toBeFalse()
            ->and(MemoryPhoto::factory()->ready()->create()->fresh()->isFailed())->toBeFalse();
    });
});

describe('web()', function () {
    test('describes the stored web variant', function () {
        /** @var TestCase $this */
        expect(readyMemoryPhoto()->web())->toEqual(
            new PhotoVariant('memories/photos/abc/web.jpg', 'image/jpeg', 1536, 2048, 412_345),
        );
    });

    test('is null while the photo is processing', function () {
        /** @var TestCase $this */
        expect(MemoryPhoto::factory()->create()->web())->toBeNull();
    });
});

describe('thumbnail()', function () {
    test('describes the stored thumbnail', function () {
        /** @var TestCase $this */
        expect(readyMemoryPhoto()->thumbnail())->toEqual(
            new PhotoVariant('memories/photos/abc/thumbnail.jpg', 'image/webp', 384, 512, 31_234),
        );
    });

    test('is null when the photo failed', function () {
        /** @var TestCase $this */
        expect(MemoryPhoto::factory()->failed()->create()->thumbnail())->toBeNull();
    });
});

describe('delete()', function () {
    test('keeps the row, marking it removed', function () {
        /** @var TestCase $this */
        $photo = MemoryPhoto::factory()->ready()->create();

        $photo->delete();

        $this->assertSoftDeleted($photo);
    });
});

describe('memory()', function () {
    test('is the memory the photo is on', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->create();
        $photo = MemoryPhoto::factory()->for($memory)->create();

        expect($photo->memory->is($memory))->toBeTrue();
    });
});
