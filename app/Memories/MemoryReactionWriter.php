<?php

namespace App\Memories;

use App\Models\Memory;
use App\Models\MemoryReaction;
use App\Models\User;
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
        if (! self::isOneEmoji($emoji)) {
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
     * One user-perceived character that is an emoji: a flag, a keycap, or a
     * pictograph with any presentation selector, skin tone or joined
     * sequence. Stray combining marks are refused so nothing draws outside
     * its button. The browser runs a looser check before sending, in
     * resources/js/memory-reactions.js.
     */
    private static function isOneEmoji(string $text): bool
    {
        if (strlen($text) > self::MAX_EMOJI_BYTES || preg_match('/^\X$/u', $text) !== 1) {
            return false;
        }

        if (preg_match('/\p{Regional_Indicator}/u', $text) === 1) {
            return preg_match('/^\p{Regional_Indicator}{2}$/u', $text) === 1;
        }

        if (str_contains($text, "\u{20E3}")) {
            return preg_match('/^[0-9#*]\x{FE0F}?\x{20E3}$/u', $text) === 1;
        }

        return preg_match('/^\p{Extended_Pictographic}/u', $text) === 1
            && preg_match('/[^\P{M}\x{FE0F}]/u', $text) === 0;
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
