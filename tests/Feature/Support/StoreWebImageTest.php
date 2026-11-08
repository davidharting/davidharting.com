<?php

use App\Support\StoreWebImage;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Tests\Support\PhotoInspector;
use Tests\TestCase;

beforeEach(function () {
    Storage::fake('tmp-for-tests');
    Storage::fake('public');
});

/**
 * Put some bytes where Livewire keeps uploads, with the JSON file it writes
 * beside each one, and return them as the upload Livewire would hand the
 * editor. Tests keep uploads on the `tmp-for-tests` disk; production keeps
 * them on the private disk.
 */
function livewireTemporaryUpload(string $contents, string $extension = 'jpg'): TemporaryUploadedFile
{
    $name = uniqid().'.'.$extension;
    Storage::disk('tmp-for-tests')->put("livewire-tmp/{$name}", $contents);
    Storage::disk('tmp-for-tests')->put("livewire-tmp/{$name}.json", '{"name":"IMG_0001.jpg"}');

    return TemporaryUploadedFile::createFromLivewire($name);
}

/**
 * The upload Livewire would hand the editor for one of the photo fixtures.
 */
function livewireTemporaryUploadOf(string $fixture): TemporaryUploadedFile
{
    return livewireTemporaryUpload(file_get_contents(PhotoInspector::fixture($fixture)), pathinfo($fixture, PATHINFO_EXTENSION));
}

describe('handle()', function () {
    test('stores an upright, sRGB, metadata-free WebP of :dataset on the public disk', function (string $fixture) {
        /** @var TestCase $this */
        $upload = livewireTemporaryUploadOf($fixture);

        $path = (new StoreWebImage)->handle($upload, 'public', 'notes/my-note');

        expect($path)->toMatch('#^notes/my-note/[0-9A-Z]{26}\.webp$#');
        $copy = PhotoInspector::inspect(Storage::disk('public')->path($path));
        expect($copy)->toMatchArray(['mime' => 'image/webp', 'width' => 1536, 'height' => 2048, 'exif_tags' => 0, 'has_gps' => false])
            ->and(PhotoInspector::near($copy['top'], [200, 100, 50]))->toBeTrue('orange is on top, in sRGB')
            ->and(PhotoInspector::near($copy['bottom'], [40, 90, 210]))->toBeTrue('blue is at the bottom, in sRGB');
    })->with(['a JPEG' => 'iphone.jpg', 'a HEIC' => 'iphone.heic']);

    test('keeps every frame of an animated GIF and strips its comment and XMP', function () {
        /** @var TestCase $this */
        $upload = livewireTemporaryUploadOf('animated.gif');

        $path = (new StoreWebImage)->handle($upload, 'public', 'notes/my-note');

        $copy = PhotoInspector::inspect(Storage::disk('public')->path($path));
        expect($path)->toEndWith('.webp')
            ->and($copy)->toMatchArray(['mime' => 'image/webp', 'width' => 2048, 'height' => 1024, 'frames' => 3, 'has_xmp' => false])
            ->and(Storage::disk('public')->get($path))->not->toContain('123 Main St');
    });

    test('deletes the original upload and the JSON file Livewire keeps beside it once the copy is stored', function () {
        /** @var TestCase $this */
        $upload = livewireTemporaryUploadOf('iphone.jpg');

        (new StoreWebImage)->handle($upload, 'public', 'notes/my-note');

        expect(Storage::disk('tmp-for-tests')->allFiles())->toBeEmpty()
            ->and(Storage::disk('public')->allFiles())->toHaveCount(1);
    });

    test('stores nothing and deletes the upload when it is not an image libvips can read', function () {
        /** @var TestCase $this */
        $upload = livewireTemporaryUpload('not really a jpeg');

        $path = (new StoreWebImage)->handle($upload, 'public', 'notes/my-note');

        expect($path)->toBeNull()
            ->and(Storage::disk('public')->allFiles())->toBeEmpty()
            ->and(Storage::disk('tmp-for-tests')->allFiles())->toBeEmpty();
    });
});
