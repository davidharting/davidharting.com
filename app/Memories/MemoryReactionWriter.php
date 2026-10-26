<?php

namespace App\Memories;

use App\Models\Memory;
use App\Models\MemoryReaction;
use App\Models\User;
use App\Support\Emoji;
use Illuminate\Database\Eloquent\Builder;
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
        $this->insert($emoji, fn () => new MemoryReaction([
            'memory_id' => $this->memory->id,
            'user_id' => $this->reactor->id,
            'emoji' => $emoji,
        ]));
    }

    /**
     * Remove one of the reactor's reactions, returning it so it can be
     * restored, or null when they hadn't left that emoji.
     */
    public function remove(string $emoji): ?MemoryReaction
    {
        $reaction = $this->reactions()->where('emoji', $emoji)->first();

        $reaction?->delete();

        return $reaction;
    }

    /**
     * Put a reaction removed from this memory back exactly as it was, with
     * its id and timestamps, so it returns to its place in the order.
     *
     * @throws ValidationException when the reactor already left the most they can.
     */
    public function restore(MemoryReaction $removed): void
    {
        if ($removed->memory_id !== $this->memory->id || $removed->user_id !== $this->reactor->id) {
            return;
        }

        $this->insert($removed->emoji, fn () => (new MemoryReaction)->forceFill($removed->only([
            'id', 'memory_id', 'user_id', 'emoji', 'created_at', 'updated_at',
        ])));
    }

    /**
     * Save the reaction that `$make` builds, unless the reactor already left
     * that emoji. The memory is locked while counting, so two reactions sent
     * at once can't both slip in under the limit.
     *
     * @param  callable(): MemoryReaction  $make
     */
    private function insert(string $emoji, callable $make): void
    {
        if (! Emoji::isOne($emoji)) {
            throw ValidationException::withMessages([
                'emoji' => 'A reaction is a single emoji.',
            ]);
        }

        DB::transaction(function () use ($emoji, $make): void {
            Memory::query()->whereKey($this->memory->id)->lockForUpdate()->sole();

            if ($this->reactions()->where('emoji', $emoji)->exists()) {
                return;
            }

            if ($this->reactions()->count() >= self::MAX_PER_PERSON) {
                throw ValidationException::withMessages([
                    'emoji' => 'You can leave up to '.self::MAX_PER_PERSON.' reactions on a memory.',
                ]);
            }

            $make()->save();
        });
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
