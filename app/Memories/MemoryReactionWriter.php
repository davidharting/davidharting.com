<?php

namespace App\Memories;

use App\Models\Memory;
use App\Models\MemoryReaction;
use App\Models\User;
use Carbon\CarbonInterface;
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

    /**
     * The longest emoji accepted, in bytes. The longest standard emoji
     * sequences are well under it.
     */
    private const int MAX_EMOJI_BYTES = 64;

    public function __construct(public User $reactor, public Memory $memory) {}

    /**
     * Add a reaction after the reactor's others. An emoji they already left
     * changes nothing.
     *
     * @throws ValidationException when it isn't one emoji, or the reactor already left the most they can.
     */
    public function add(string $emoji): void
    {
        $this->insert($emoji, now());
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
     * Put a removed reaction back in its place, by the time it was first
     * added.
     *
     * @throws ValidationException when it isn't one emoji, or the reactor already left the most they can.
     */
    public function restore(string $emoji, CarbonInterface $addedAt): void
    {
        $this->insert($emoji, $addedAt);
    }

    /**
     * The memory is locked while counting, so two reactions sent at once
     * can't both slip in under the limit.
     */
    private function insert(string $emoji, CarbonInterface $addedAt): void
    {
        if (! self::isOneEmoji($emoji)) {
            throw ValidationException::withMessages([
                'emoji' => 'A reaction is a single emoji.',
            ]);
        }

        DB::transaction(function () use ($emoji, $addedAt): void {
            Memory::query()->whereKey($this->memory->id)->lockForUpdate()->sole();

            if ($this->reactions()->where('emoji', $emoji)->exists()) {
                return;
            }

            if ($this->reactions()->count() >= self::MAX_PER_PERSON) {
                throw ValidationException::withMessages([
                    'emoji' => 'You can leave up to '.self::MAX_PER_PERSON.' reactions on a memory.',
                ]);
            }

            $reaction = new MemoryReaction([
                'memory_id' => $this->memory->id,
                'user_id' => $this->reactor->id,
                'emoji' => $emoji,
            ]);
            $reaction->created_at = $addedAt;
            $reaction->save();
        });
    }

    /**
     * One user-perceived character that is an emoji: a pictograph with any
     * skin tone or joined sequence, a flag, or a keycap.
     */
    private static function isOneEmoji(string $text): bool
    {
        return strlen($text) <= self::MAX_EMOJI_BYTES
            && preg_match('/^\X$/u', $text) === 1
            && preg_match('/[\p{Extended_Pictographic}\p{Regional_Indicator}\x{20E3}]/u', $text) === 1;
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
