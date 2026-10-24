<?php

use App\Jobs\ProcessMemoryPhoto;
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

test('stores an upright, sRGB web variant and thumbnail without metadata from an iPhone :dataset', function (string $fixture) {
    /** @var TestCase $this */
    $photo = photoWithOriginal($fixture);

    ProcessMemoryPhoto::dispatch($photo);

    $photo->refresh();
    expect($photo->isReady())->toBeTrue()
        ->and($photo->web()->path)->toBe('memories/photos/01JTESTPHOTO/web.jpg')
        ->and($photo->thumbnail()->path)->toBe('memories/photos/01JTESTPHOTO/thumbnail.jpg');

    $disk = Storage::disk('private');
    foreach (['web' => [$photo->web(), 1536, 2048], 'thumbnail' => [$photo->thumbnail(), 384, 512]] as $name => [$variant, $width, $height]) {
        $copy = PhotoInspector::inspect($disk->path($variant->path));

        expect([$variant->width, $variant->height])->toBe([$width, $height], "{$name} size is recorded")
            ->and($variant->mimeType)->toBe('image/jpeg')
            ->and($variant->bytes)->toBe($disk->size($variant->path), "{$name} bytes are recorded")
            ->and([$copy['width'], $copy['height']])->toBe([$width, $height], "{$name} is upright at that size")
            ->and($copy['mime'])->toBe('image/jpeg')
            ->and($copy['exif_tags'])->toBe(0, "{$name} has no EXIF or GPS")
            ->and(PhotoInspector::near($copy['top'], [200, 100, 50]))->toBeTrue("{$name} has orange converted to sRGB on top")
            ->and(PhotoInspector::near($copy['bottom'], [40, 90, 210]))->toBeTrue("{$name} has blue converted to sRGB at the bottom");
    }

    expect(PhotoInspector::inspect($disk->path($photo->original_path))['has_gps'])->toBeTrue('the original is kept as uploaded');
})->with(['iphone.jpg', 'iphone.heic']);

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

    expect($photo->refresh()->web()->path)->toEndWith('/web.jpg')
        ->and(Storage::disk('private')->allFiles())->toBe([]);
});
