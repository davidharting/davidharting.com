<?php

namespace App\Memories;

use App\Models\Memory;
use App\Models\MemoryReaction;
use App\Models\User;
use App\Support\Emoji;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds, removes and restores one person's reactions on one memory.
 *
 * This class performs no authorization. The caller is expected to have
 * checked that the reactor may react to the memory.
 */
final readonly class MemoryReactionWriter
{
    /**
     * The most reactions one person may leave on one memory.
     */
    public const int MAX_PER_PERSON = 5;

    public function __construct(public User $reactor, public Memory $memory) {}

    /**
     * Add a reaction after the reactor's others. An emoji they already left
     * changes nothing.
     *
     * @throws ValidationException when it isn't one emoji, or the reactor already left the most they can.
     */
    public function add(string $emoji): void
    {
        if (! Emoji::isEmoji($emoji)) {
            throw ValidationException::withMessages([
                'emoji' => 'A reaction is a single emoji.',
            ]);
        }

        $reaction = MemoryReaction::createOrFirst([
            'memory_id' => $this->memory->id,
            'user_id' => $this->reactor->id,
            'emoji' => $emoji,
        ]);

        if ($reaction->wasRecentlyCreated && $this->isOverLimit()) {
            $reaction->forceDelete();

            throw $this->overLimit();
        }
    }

    /**
     * Remove one of the reactor's reactions by soft-deleting it, returning it
     * so it can be restored, or null when they hadn't left that emoji.
     */
    public function remove(string $emoji): ?MemoryReaction
    {
        $reaction = $this->reactions()->where('emoji', $emoji)->first();

        $reaction?->delete();

        return $reaction;
    }

    /**
     * Put one of the reactor's removed reactions back. It keeps its id and
     * timestamps, so it returns to its place in the order. A reaction they
     * have since left again, or that isn't theirs on this memory, is quietly
     * left alone.
     *
     * @throws ValidationException when the reactor already left the most they can.
     */
    public function restore(int $reactionId): void
    {
        $reaction = $this->reactions()->onlyTrashed()->find($reactionId);

        if ($reaction === null) {
            return;
        }

        try {
            DB::transaction(fn () => $reaction->restore());
        } catch (UniqueConstraintViolationException) {
            return;
        }

        if ($this->isOverLimit()) {
            $reaction->delete();

            throw $this->overLimit();
        }
    }

    /**
     * Counted after saving rather than under a lock. When two reactions race
     * past the limit, each sees too many and backs itself out, so the limit
     * holds; at worst both are refused.
     */
    private function isOverLimit(): bool
    {
        return $this->reactions()->count() > self::MAX_PER_PERSON;
    }

    private function overLimit(): ValidationException
    {
        return ValidationException::withMessages([
            'emoji' => 'You can leave up to '.self::MAX_PER_PERSON.' reactions on a memory.',
        ]);
    }

    /**
     * @return Builder<MemoryReaction>
     */
    private function reactions(): Builder
    {
        return MemoryReaction::query()
            ->where('memory_id', $this->memory->id)
            ->where('user_id', $this->reactor->id);
    }
}
