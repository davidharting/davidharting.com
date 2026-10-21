<?php

use App\Models\MemoryPhoto;
use Tests\TestCase;

describe('definition()', function () {
    test('gives every photo its own original file', function () {
        /** @var TestCase $this */
        $photos = MemoryPhoto::factory()->count(3)->create();

        expect($photos->pluck('original_path')->unique())->toHaveCount(3);
    });
});

describe('captioned()', function () {
    test('adds the given caption', function () {
        /** @var TestCase $this */
        $photo = MemoryPhoto::factory()->captioned('The pumpkin we picked')->create();

        expect($photo->caption)->toBe('The pumpkin we picked');
    });
});

describe('ready()', function () {
    test('points both variants at the original\'s directory', function () {
        /** @var TestCase $this */
        $photo = MemoryPhoto::factory()->ready()->create()->fresh();
        $directory = dirname($photo->original_path);

        expect(dirname($photo->web()->path))->toBe($directory)
            ->and(dirname($photo->thumbnail()->path))->toBe($directory);
    });

    test('fits a landscape thumbnail in 512 pixels, keeping its shape', function () {
        /** @var TestCase $this */
        $photo = MemoryPhoto::factory()->ready(width: 2048, height: 1536)->create()->fresh();

        expect($photo->thumbnail()->width)->toBe(512)
            ->and($photo->thumbnail()->height)->toBe(384);
    });

    test('fits a portrait thumbnail in 512 pixels, keeping its shape', function () {
        /** @var TestCase $this */
        $photo = MemoryPhoto::factory()->ready(width: 1536, height: 2048)->create()->fresh();

        expect($photo->thumbnail()->width)->toBe(384)
            ->and($photo->thumbnail()->height)->toBe(512);
    });
});

describe('failed()', function () {
    test('records when and why processing gave up', function () {
        /** @var TestCase $this */
        $photo = MemoryPhoto::factory()->failed('vips ran out of memory')->create();

        expect($photo->failed_at)->not->toBeNull()
            ->and($photo->failure)->toBe('vips ran out of memory');
    });
});
