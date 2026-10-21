<?php

use App\Memories\PhotoVariant;
use App\Models\Memory;
use App\Models\MemoryPhoto;
use Tests\TestCase;

describe('state', function () {
    test('is processing until its copies are made', function () {
        /** @var TestCase $this */
        $photo = MemoryPhoto::factory()->create();

        expect($photo->isProcessing())->toBeTrue()
            ->and($photo->isReady())->toBeFalse()
            ->and($photo->isFailed())->toBeFalse();
    });

    test('is ready once its copies are stored', function () {
        /** @var TestCase $this */
        $photo = MemoryPhoto::factory()->ready()->create();

        expect($photo->fresh()->isReady())->toBeTrue()
            ->and($photo->fresh()->isProcessing())->toBeFalse()
            ->and($photo->fresh()->isFailed())->toBeFalse();
    });

    test('is failed once processing gives up', function () {
        /** @var TestCase $this */
        $photo = MemoryPhoto::factory()->failed('vips ran out of memory')->create();

        expect($photo->fresh()->isFailed())->toBeTrue()
            ->and($photo->fresh()->isProcessing())->toBeFalse()
            ->and($photo->fresh()->isReady())->toBeFalse()
            ->and($photo->fresh()->failure)->toBe('vips ran out of memory');
    });
});

describe('web()', function () {
    test('describes the web-sized copy once the photo is ready', function () {
        /** @var TestCase $this */
        $photo = MemoryPhoto::factory()->ready(width: 1536, height: 2048)->create();

        $web = $photo->fresh()->web();

        expect($web)->toBeInstanceOf(PhotoVariant::class)
            ->and($web->path)->toBe(dirname($photo->original_path).'/web.jpg')
            ->and($web->mimeType)->toBe('image/jpeg')
            ->and($web->width)->toBe(1536)
            ->and($web->height)->toBe(2048);
    });

    test('is null while the photo is processing', function () {
        /** @var TestCase $this */
        $photo = MemoryPhoto::factory()->create();

        expect($photo->web())->toBeNull();
    });
});

describe('thumbnail()', function () {
    test('describes the thumbnail once the photo is ready', function () {
        /** @var TestCase $this */
        $photo = MemoryPhoto::factory()->ready(width: 2048, height: 1536)->create();

        $thumbnail = $photo->fresh()->thumbnail();

        expect($thumbnail->path)->toBe(dirname($photo->original_path).'/thumbnail.jpg')
            ->and($thumbnail->width)->toBe(512)
            ->and($thumbnail->height)->toBe(384);
    });

    test('is null when the photo failed', function () {
        /** @var TestCase $this */
        $photo = MemoryPhoto::factory()->failed()->create();

        expect($photo->thumbnail())->toBeNull();
    });
});

describe('delete()', function () {
    test('keeps the row, marking it removed', function () {
        /** @var TestCase $this */
        $photo = MemoryPhoto::factory()->ready()->create();

        $photo->delete();

        $this->assertSoftDeleted($photo);
        expect(MemoryPhoto::withTrashed()->find($photo->id)->original_path)->toBe($photo->original_path);
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
