<?php

namespace App\Memories;

use App\Jobs\ProcessMemoryPhoto;
use App\Models\Memory;
use App\Models\MemoryPhoto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Captions, orders, removes and restores the photos of one memory.
 *
 * None of this touches the memory's `lock_version`: the last change saved
 * wins. A photo that isn't on the memory (another device removed it, say) is
 * quietly left alone.
 *
 * This class performs no authorization. The caller is expected to have
 * checked that the author may write the memory.
 */
final readonly class MemoryPhotos
{
    /**
     * The longest caption, in characters.
     */
    public const int MAX_CAPTION_LENGTH = 500;

    public function __construct(public Memory $memory) {}

    /**
     * Set a photo's caption. A blank caption removes it.
     *
     * @throws ValidationException when the caption is too long.
     */
    public function caption(int $photoId, string $caption): void
    {
        $caption = trim($caption);

        if (mb_strlen($caption) > self::MAX_CAPTION_LENGTH) {
            throw ValidationException::withMessages([
                'caption' => 'A caption can be up to '.number_format(self::MAX_CAPTION_LENGTH).' characters.',
            ]);
        }

        $this->find($photoId)?->update(['caption' => $caption === '' ? null : $caption]);
    }

    /**
     * Move a photo to a place in the order, counted from 0, and number the
     * photos again from 0. A place past the end means last.
     */
    public function move(int $photoId, int $position): void
    {
        $this->rearrange(function (Collection $photos) use ($photoId, $position): ?Collection {
            $photo = $photos->firstWhere('id', $photoId);

            if ($photo === null) {
                return null;
            }

            $others = $photos->reject(fn (MemoryPhoto $other) => $other->is($photo))->values();
            $others->splice(max(0, min($position, $others->count())), 0, [$photo]);

            return $others;
        });
    }

    /**
     * Remove a photo by soft-deleting it, so it can be restored. Returns
     * whether there was a photo to remove.
     *
     * @throws ValidationException when it is the last thing in a shared memory.
     */
    public function remove(int $photoId): bool
    {
        $photo = $this->find($photoId);

        if ($photo === null) {
            return false;
        }

        $this->memory->refresh();

        if ($this->memory->isShared() && trim($this->memory->body) === '' && $this->memory->photos()->count() === 1) {
            throw ValidationException::withMessages([
                'photo' => "A shared memory can't be empty, so its last photo can't be removed until it has some text.",
            ]);
        }

        $photo->delete();

        return true;
    }

    /**
     * Put a removed photo back where it was among the photos still there.
     *
     * @throws ValidationException when the memory already has the most photos it can have.
     */
    public function restore(int $photoId): void
    {
        $this->rearrange(function (Collection $photos) use ($photoId): ?Collection {
            $photo = MemoryPhoto::onlyTrashed()->where('memory_id', $this->memory->id)->find($photoId);

            if ($photo === null) {
                return null;
            }

            if ($photos->count() >= AddMemoryPhoto::MAX_PHOTOS) {
                throw ValidationException::withMessages([
                    'photo' => 'A memory can have up to '.AddMemoryPhoto::MAX_PHOTOS.' photos, so that one can\'t come back.',
                ]);
            }

            $photo->restore();
            $before = $photos->filter(fn (MemoryPhoto $other) => $other->position < $photo->position)->count();
            $photos->splice($before, 0, [$photo]);

            return $photos;
        });
    }

    /**
     * Process a photo whose processing failed again from its original.
     */
    public function retry(int $photoId): void
    {
        $photo = $this->find($photoId);

        if ($photo === null || ! $photo->isFailed()) {
            return;
        }

        $photo->update(['failed_at' => null, 'failure' => null]);

        ProcessMemoryPhoto::dispatch($photo);
    }

    private function find(int $photoId): ?MemoryPhoto
    {
        return MemoryPhoto::query()->where('memory_id', $this->memory->id)->find($photoId);
    }

    /**
     * Lock the memory, hand its photos in order to the arrangement, and
     * number whatever it returns from 0. An arrangement that returns null
     * changes nothing. The lock keeps an upload from landing in between.
     *
     * @param  callable(Collection<int, MemoryPhoto>): ?Collection<int, MemoryPhoto>  $arrange
     */
    private function rearrange(callable $arrange): void
    {
        DB::transaction(function () use ($arrange): void {
            Memory::query()->whereKey($this->memory->id)->lockForUpdate()->sole();

            $arranged = $arrange($this->memory->photos()->get());

            foreach ($arranged ?? [] as $position => $photo) {
                if ($photo->position !== $position) {
                    $photo->update(['position' => $position]);
                }
            }
        });
    }
}
