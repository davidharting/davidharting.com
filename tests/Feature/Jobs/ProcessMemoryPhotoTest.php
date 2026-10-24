<?php

use App\Jobs\ProcessMemoryPhoto;
use App\Memories\PhotoVariant;
use App\Models\MemoryPhoto;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PhotoInspector;
use Tests\TestCase;

beforeEach(function () {
    Storage::fake('private');
});

function photoWithOriginal(string $fixture): MemoryPhoto
{
    $extension = pathinfo($fixture, PATHINFO_EXTENSION);
    $photo = MemoryPhoto::factory()->create([
        'original_path' => "memories/photos/01JTESTPHOTO/original.{$extension}",
    ]);
    Storage::disk('private')->put($photo->original_path, file_get_contents(PhotoInspector::fixture($fixture)));

    return $photo;
}

/**
 * Expect a variant to be a portrait JPEG of the given size, recorded as
 * stored, with no metadata and its colours converted to sRGB.
 */
function expectUprightSrgbJpeg(PhotoVariant $variant, int $width, int $height): void
{
    $disk = Storage::disk('private');
    $copy = PhotoInspector::inspect($disk->path($variant->path));

    expect($variant)->toMatchObject(['mimeType' => 'image/jpeg', 'width' => $width, 'height' => $height, 'bytes' => $disk->size($variant->path)])
        ->and($copy)->toMatchArray(['mime' => 'image/jpeg', 'width' => $width, 'height' => $height, 'exif_tags' => 0])
        ->and(PhotoInspector::near($copy['top'], [200, 100, 50]))->toBeTrue('orange is on top, in sRGB')
        ->and(PhotoInspector::near($copy['bottom'], [40, 90, 210]))->toBeTrue('blue is at the bottom, in sRGB');
}

test('makes an upright, sRGB, metadata-free web variant and thumbnail from an iPhone :dataset', function (string $fixture) {
    /** @var TestCase $this */
    $photo = photoWithOriginal($fixture);

    ProcessMemoryPhoto::dispatch($photo);

    $photo->refresh();
    expect($photo->web()->path)->toBe('memories/photos/01JTESTPHOTO/web.jpg')
        ->and($photo->thumbnail()->path)->toBe('memories/photos/01JTESTPHOTO/thumbnail.jpg');
    expectUprightSrgbJpeg($photo->web(), 1536, 2048);
    expectUprightSrgbJpeg($photo->thumbnail(), 384, 512);
})->with(['iphone.jpg', 'iphone.heic']);

test('keeps the original as uploaded', function () {
    /** @var TestCase $this */
    $photo = photoWithOriginal('iphone.jpg');

    ProcessMemoryPhoto::dispatch($photo);

    expect(PhotoInspector::inspect(Storage::disk('private')->path($photo->original_path))['has_gps'])->toBeTrue();
});

test('fails the photo on the first try when libvips cannot read the original', function () {
    /** @var TestCase $this */
    $photo = MemoryPhoto::factory()->create();
    Storage::disk('private')->put($photo->original_path, 'not really a jpeg');

    ProcessMemoryPhoto::dispatch($photo);

    $photo->refresh();
    expect($photo->isFailed())->toBeTrue()
        ->and($photo->failure)->toContain('vipsthumbnail failed')
        ->and($photo->isReady())->toBeFalse();
});

test('leaves the photo processing when the original cannot be read, so the job is retried', function () {
    /** @var TestCase $this */
    $photo = MemoryPhoto::factory()->create();

    expect(fn () => (new ProcessMemoryPhoto($photo))->handle())->toThrow(RuntimeException::class, "Couldn't read");
    expect($photo->refresh()->isProcessing())->toBeTrue();
});

test('records why the photo failed once it runs out of tries', function () {
    /** @var TestCase $this */
    $photo = MemoryPhoto::factory()->create();

    (new ProcessMemoryPhoto($photo))->failed(new RuntimeException("Couldn't read the original."));

    expect($photo->refresh()->isFailed())->toBeTrue()
        ->and($photo->failure)->toBe("Couldn't read the original.");
});

test('leaves a ready photo alone', function () {
    /** @var TestCase $this */
    $photo = MemoryPhoto::factory()->ready()->create();

    ProcessMemoryPhoto::dispatch($photo);

    expect($photo->refresh()->updated_at)->toEqual($photo->updated_at)
        ->and(Storage::disk('private')->allFiles())->toBe([]);
});
