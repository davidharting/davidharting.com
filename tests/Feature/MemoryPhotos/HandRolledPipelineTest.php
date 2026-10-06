<?php

use App\Actions\MemoryPhotos\AddMemoryPhoto;
use App\Enum\MemoryPhotoStatus;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\PhotoInspector;
use Tests\TestCase;

/*
 * PROTOTYPE (#251), variant A: the hand-rolled memory_photos pipeline, run end to end on the real vips CLI.
 * The queue is sync in tests, so the job runs inline.
 */

beforeEach(function () {
    Storage::fake('private', ['serve' => true]);
});

describe('pipeline', function () {
    test('makes upright, sRGB, stripped copies from an iPhone :dataset', function (string $fixture) {
        /** @var TestCase $this */
        $memory = Memory::factory()->create();

        $photo = app(AddMemoryPhoto::class)->handle($memory, PhotoInspector::upload($fixture), 'Fireworks');

        $photo->refresh();
        expect($photo->status)->toBe(MemoryPhotoStatus::READY);

        $disk = Storage::disk('private');
        expect($disk->exists($photo->path('original')))->toBeTrue();

        $original = PhotoInspector::inspect($disk->path($photo->path('original')));
        $web = PhotoInspector::inspect($disk->path($photo->path('web')));
        $thumb = PhotoInspector::inspect($disk->path($photo->path('thumb')));
        dump(compact('original', 'web', 'thumb'));

        expect($original['has_gps'])->toBeTrue('original is kept as uploaded');

        foreach (['web' => [$web, 2048], 'thumb' => [$thumb, 512]] as $variant => [$copy, $maxEdge]) {
            expect($copy['mime'])->toBe('image/jpeg', $variant)
                ->and(max($copy['width'], $copy['height']))->toBe($maxEdge, $variant)
                ->and($copy['upright'])->toBeTrue("{$variant} is upright")
                ->and($copy['has_gps'])->toBeFalse("{$variant} has no GPS")
                ->and($copy['exif_tags'])->toBe(0, "{$variant} has no EXIF")
                ->and(PhotoInspector::near($copy['top'], [200, 100, 50]))->toBeTrue("{$variant} orange converted to sRGB")
                ->and(PhotoInspector::near($copy['bottom'], [40, 90, 210]))->toBeTrue("{$variant} blue converted to sRGB");
        }

        expect([$photo->width, $photo->height])->toBe([1536, 2048]);
    })->with(['iphone.jpg', 'iphone.heic']);
});

describe('limits and lifecycle', function () {
    test('rejects an eleventh photo', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->create();
        foreach (range(1, 10) as $i) {
            app(AddMemoryPhoto::class)->handle($memory, PhotoInspector::upload('iphone.jpg'));
        }

        expect(fn () => app(AddMemoryPhoto::class)->handle($memory, PhotoInspector::upload('iphone.jpg')))
            ->toThrow(ValidationException::class);
        expect($memory->photos()->pluck('position')->all())->toBe(range(0, 9));
    });

    test('soft-deleting a photo keeps its files and frees a slot', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->create();
        $photo = app(AddMemoryPhoto::class)->handle($memory, PhotoInspector::upload('iphone.jpg'));

        $photo->delete();

        expect($memory->photos()->count())->toBe(0)
            ->and($memory->photos()->withTrashed()->count())->toBe(1)
            ->and(Storage::disk('private')->exists($photo->path('original')))->toBeTrue()
            ->and(Storage::disk('private')->exists($photo->path('web')))->toBeTrue();
    });

    test('a failing conversion marks the photo failed', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->create();
        $notAnImage = UploadedFile::fake()->createWithContent('broken.jpg', 'not really a jpeg');

        try {
            app(AddMemoryPhoto::class)->handle($memory, $notAnImage);
        } catch (Throwable) {
            // The sync queue rethrows; on a real worker the job retries, then failed() runs.
        }

        $photo = $memory->photos()->first();
        expect($photo->status)->toBe(MemoryPhotoStatus::FAILED)
            ->and($photo->failure)->toContain('vipsthumbnail failed');
    });
});

describe('serving', function () {
    test('redirects the owner to a short-lived URL', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->create();
        $photo = app(AddMemoryPhoto::class)->handle($memory, PhotoInspector::upload('iphone.jpg'));

        $this->actingAs($memory->user)
            ->get(route('memory-photos.show', [$photo, 'web']))
            ->assertRedirectContains('expiration=');
    });

    test('404s for anyone else, logged in or not', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->create();
        $photo = app(AddMemoryPhoto::class)->handle($memory, PhotoInspector::upload('iphone.jpg'));

        $this->get(route('memory-photos.show', [$photo, 'web']))->assertNotFound();
        $this->actingAs(User::factory()->create())
            ->get(route('memory-photos.show', [$photo, 'web']))
            ->assertNotFound();
    });
});
