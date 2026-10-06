<?php

use App\Models\Memory;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Tests\Support\PhotoInspector;
use Tests\TestCase;

/*
 * PROTOTYPE (#251), variant B: Spatie Media Library on the same fixtures, under three engines.
 * The queue is sync in tests, so conversions run inline.
 */

function useEngine(string $engine): void
{
    config([
        'media-library.prototype_engine' => $engine,
        'media-library.image_driver' => $engine === 'vips' ? 'vips' : 'gd',
    ]);
}

beforeEach(function () {
    Storage::fake('private', ['serve' => true]);
    // RefreshDatabase wraps each test in a transaction that never commits, so after-commit conversions would never run.
    config(['media-library.queue_conversions_after_database_commit' => false]);
});

describe('pipeline', function () {
    test('records what each engine makes of an iPhone photo', function (string $engine, string $fixture) {
        /** @var TestCase $this */
        useEngine($engine);
        $memory = Memory::factory()->create();

        try {
            $media = $memory->addMedia(PhotoInspector::upload($fixture))
                ->withCustomProperties(['caption' => 'Fireworks'])
                ->toMediaCollection('photos');
        } catch (Throwable $exception) {
            fwrite(STDERR, sprintf("\n%-5s %-12s FAILED: %s", $engine, $fixture, $exception->getMessage()));
            expect(true)->toBeTrue();

            return;
        }

        $media->refresh();
        $row = [];
        foreach (['web' => 2048, 'thumb' => 512] as $conversion => $maxEdge) {
            if (! $media->hasGeneratedConversion($conversion)) {
                $row[] = "{$conversion}: not generated";

                continue;
            }

            $copy = PhotoInspector::inspect($media->getPath($conversion));
            $row[] = sprintf(
                '%s: %dx%d upright=%s exif=%d gps=%s srgb=%s icc=%s',
                $conversion,
                $copy['width'],
                $copy['height'],
                $copy['upright'] ? 'yes' : 'NO',
                $copy['exif_tags'],
                $copy['has_gps'] ? 'YES' : 'no',
                PhotoInspector::near($copy['top'], [200, 100, 50]) && PhotoInspector::near($copy['bottom'], [40, 90, 210]) ? 'yes' : 'NO '.json_encode([$copy['top'], $copy['bottom']]),
                $copy['icc'] ?? '-',
            );
        }

        fwrite(STDERR, sprintf("\n%-5s %-12s %s", $engine, $fixture, implode(' | ', $row)));
        expect($media->getCustomProperty('caption'))->toBe('Fireworks');
    })->with(['cli', 'gd', 'vips'])->with(['iphone.jpg', 'iphone.heic']);
});

describe('limits and lifecycle', function () {
    test('rejects an eleventh photo', function () {
        /** @var TestCase $this */
        useEngine('cli');
        $memory = Memory::factory()->create();
        foreach (range(1, 10) as $i) {
            $memory->addMedia(PhotoInspector::upload('iphone.jpg'))->toMediaCollection('photos');
        }

        expect(fn () => $memory->addMedia(PhotoInspector::upload('iphone.jpg'))->toMediaCollection('photos'))
            ->toThrow(FileUnacceptableForCollection::class);
        expect($memory->fresh()->getMedia('photos')->pluck('order_column')->all())->toBe(range(1, 10));
    });

    test('soft-deleting a photo keeps its files and frees a slot', function () {
        /** @var TestCase $this */
        useEngine('cli');
        $memory = Memory::factory()->create();
        $media = $memory->addMedia(PhotoInspector::upload('iphone.jpg'))->toMediaCollection('photos');
        $originalPath = $media->getPath();
        $webPath = $media->getPath('web');

        $media->delete();

        expect($memory->fresh()->getMedia('photos'))->toHaveCount(0)
            ->and(file_exists($originalPath))->toBeTrue()
            ->and(file_exists($webPath))->toBeTrue();
    });
});

describe('serving', function () {
    test('redirects the owner to a short-lived URL', function () {
        /** @var TestCase $this */
        useEngine('cli');
        $memory = Memory::factory()->create();
        $media = $memory->addMedia(PhotoInspector::upload('iphone.jpg'))->toMediaCollection('photos');

        $this->actingAs($memory->user)
            ->get(route('memory-media.show', [$media, 'web']))
            ->assertRedirectContains('expiration=');
    });

    test('404s for anyone else, logged in or not', function () {
        /** @var TestCase $this */
        useEngine('cli');
        $memory = Memory::factory()->create();
        $media = $memory->addMedia(PhotoInspector::upload('iphone.jpg'))->toMediaCollection('photos');

        $this->get(route('memory-media.show', [$media, 'web']))->assertNotFound();
        $this->actingAs(User::factory()->create())
            ->get(route('memory-media.show', [$media, 'web']))
            ->assertNotFound();
    });
});
