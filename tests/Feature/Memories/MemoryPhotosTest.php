<?php

use App\Jobs\ProcessMemoryPhoto;
use App\Memories\AddMemoryPhoto;
use App\Memories\MemoryPhotos;
use App\Models\Memory;
use App\Models\MemoryPhoto;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->memory = Memory::factory()->create(['body' => 'Some text', 'lock_version' => 4]);
    $this->photos = new MemoryPhotos($this->memory);
});

/**
 * Give the memory photos at positions 0, 1, 2… and return them in order.
 *
 * @return list<MemoryPhoto>
 */
function photosOn(Memory $memory, int $count): array
{
    return MemoryPhoto::factory()->for($memory)->count($count)
        ->sequence(fn ($sequence) => ['position' => $sequence->index])
        ->create()
        ->all();
}

/**
 * The ids of the memory's photos in order, removed ones left out.
 *
 * @return list<int>
 */
function photoOrder(Memory $memory): array
{
    return $memory->photos()->pluck('id')->all();
}

describe('caption()', function () {
    test('sets the caption without bumping the memory\'s version', function () {
        /** @var TestCase $this */
        [$photo] = photosOn($this->memory, 1);

        $this->photos->caption($photo->id, '  The pumpkin patch  ');

        expect($photo->fresh()->caption)->toBe('The pumpkin patch')
            ->and($this->memory->fresh()->lock_version)->toBe(4);
    });

    test('removes the caption when it is blank', function () {
        /** @var TestCase $this */
        $photo = MemoryPhoto::factory()->for($this->memory)->captioned('Before')->create();

        $this->photos->caption($photo->id, ' ');

        expect($photo->fresh()->caption)->toBeNull();
    });

    test('refuses a caption longer than a caption can be', function () {
        /** @var TestCase $this */
        $photo = MemoryPhoto::factory()->for($this->memory)->captioned('Before')->create();

        expect(fn () => $this->photos->caption($photo->id, str_repeat('a', MemoryPhotos::MAX_CAPTION_LENGTH + 1)))
            ->toThrow(ValidationException::class);

        expect($photo->fresh()->caption)->toBe('Before');
    });

    test('leaves photos of other memories alone', function () {
        /** @var TestCase $this */
        $someoneElses = MemoryPhoto::factory()->captioned('Not yours')->create();

        $this->photos->caption($someoneElses->id, 'Mine now');

        expect($someoneElses->fresh()->caption)->toBe('Not yours');
    });

    test('quietly does nothing to a removed photo', function () {
        /** @var TestCase $this */
        [$photo] = photosOn($this->memory, 1);
        $photo->delete();

        $this->photos->caption($photo->id, 'Too late');

        expect($photo->fresh()->caption)->toBeNull();
    });
});

describe('move()', function () {
    test('moves a photo to a new place and numbers the photos again', function () {
        /** @var TestCase $this */
        [$first, $second, $third] = photosOn($this->memory, 3);

        $this->photos->move($third->id, 0);

        expect(photoOrder($this->memory))->toBe([$third->id, $first->id, $second->id])
            ->and($this->memory->photos()->pluck('position')->all())->toBe([0, 1, 2]);
    });

    test('moves a photo past the end to last', function () {
        /** @var TestCase $this */
        [$first, $second, $third] = photosOn($this->memory, 3);

        $this->photos->move($first->id, 7);

        expect(photoOrder($this->memory))->toBe([$second->id, $third->id, $first->id]);
    });

    test('closes the gap a removed photo left', function () {
        /** @var TestCase $this */
        [$first, $second, $third] = photosOn($this->memory, 3);
        $second->delete();

        $this->photos->move($third->id, 0);

        expect($this->memory->photos()->pluck('position', 'id')->all())->toBe([$third->id => 0, $first->id => 1]);
    });

    test('leaves photos of other memories alone', function () {
        /** @var TestCase $this */
        [$first, $second] = photosOn($this->memory, 2);
        $someoneElses = MemoryPhoto::factory()->create(['position' => 5]);

        $this->photos->move($someoneElses->id, 0);

        expect(photoOrder($this->memory))->toBe([$first->id, $second->id])
            ->and($someoneElses->fresh()->position)->toBe(5);
    });
});

describe('remove()', function () {
    test('soft-deletes the photo', function () {
        /** @var TestCase $this */
        [$first, $second] = photosOn($this->memory, 2);

        expect($this->photos->remove($first->id))->toBeTrue()
            ->and(photoOrder($this->memory))->toBe([$second->id])
            ->and($first->fresh()->trashed())->toBeTrue();
    });

    test('refuses to remove the last thing in a shared memory', function () {
        /** @var TestCase $this */
        $shared = Memory::factory()->shared()->create(['body' => '']);
        [$photo] = photosOn($shared, 1);

        expect(fn () => (new MemoryPhotos($shared))->remove($photo->id))
            ->toThrow(ValidationException::class);

        expect($photo->fresh()->trashed())->toBeFalse();
    });

    test('removes the last photo of a shared memory that has text', function () {
        /** @var TestCase $this */
        $shared = Memory::factory()->shared()->create(['body' => 'Still has words']);
        [$photo] = photosOn($shared, 1);

        (new MemoryPhotos($shared))->remove($photo->id);

        expect($photo->fresh()->trashed())->toBeTrue();
    });

    test('says so when there was no photo to remove', function () {
        /** @var TestCase $this */
        $someoneElses = MemoryPhoto::factory()->create();

        expect($this->photos->remove($someoneElses->id))->toBeFalse()
            ->and($someoneElses->fresh()->trashed())->toBeFalse();
    });
});

describe('restore()', function () {
    test('puts a removed photo back where it was', function () {
        /** @var TestCase $this */
        [$first, $second, $third] = photosOn($this->memory, 3);
        $this->photos->remove($second->id);

        $this->photos->restore($second->id);

        expect(photoOrder($this->memory))->toBe([$first->id, $second->id, $third->id]);
    });

    test('puts it back among photos that moved in the meantime', function () {
        /** @var TestCase $this */
        [$first, $second, $third] = photosOn($this->memory, 3);
        $this->photos->remove($second->id);
        $this->photos->move($third->id, 0);

        $this->photos->restore($second->id);

        expect(photoOrder($this->memory))->toBe([$third->id, $second->id, $first->id])
            ->and($this->memory->photos()->pluck('position')->all())->toBe([0, 1, 2]);
    });

    test('refuses when the memory already has the most photos it can have', function () {
        /** @var TestCase $this */
        [$removed] = photosOn($this->memory, 1);
        $removed->delete();
        MemoryPhoto::factory()->for($this->memory)->count(AddMemoryPhoto::MAX_PHOTOS)->create(['position' => 1]);

        expect(fn () => $this->photos->restore($removed->id))
            ->toThrow(ValidationException::class);

        expect($removed->fresh()->trashed())->toBeTrue();
    });

    test('leaves removed photos of other memories alone', function () {
        /** @var TestCase $this */
        $someoneElses = MemoryPhoto::factory()->create();
        $someoneElses->delete();

        $this->photos->restore($someoneElses->id);

        expect($someoneElses->fresh()->trashed())->toBeTrue();
    });
});

describe('retry()', function () {
    test('clears the failure and queues processing again', function () {
        /** @var TestCase $this */
        Queue::fake();
        $photo = MemoryPhoto::factory()->for($this->memory)->failed()->create();

        $this->photos->retry($photo->id);

        expect($photo->fresh()->isProcessing())->toBeTrue();
        Queue::assertPushed(ProcessMemoryPhoto::class, fn (ProcessMemoryPhoto $job) => $job->photo->is($photo));
    });

    test('leaves a photo that hasn\'t failed alone', function () {
        /** @var TestCase $this */
        Queue::fake();
        $photo = MemoryPhoto::factory()->for($this->memory)->ready()->create();

        $this->photos->retry($photo->id);

        Queue::assertNothingPushed();
    });
});
