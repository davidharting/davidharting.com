<?php

namespace App\Memories;

use App\Models\Memory;
use App\Models\User;
use App\Support\CalendarMonth;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The reveal of one month: the moment both participants' shared memories
 * become readable to each other. It is derived from the month's memories and
 * never stored.
 *
 * A month reveals when both have shared, or at its deadline, whichever comes
 * first. A memory shared after the reveal is readable as soon as it is shared.
 */
final readonly class Reveal
{
    /**
     * @param  Collection<int, Memory>  $memories  Every memory written for the month.
     */
    public function __construct(
        public CalendarMonth $month,
        public Collection $memories,
    ) {}

    /**
     * When the month revealed, or null while it hasn't.
     */
    public function revealedAt(): ?CarbonImmutable
    {
        $deadline = $this->month->revealDeadline();
        $shared = $this->memories->filter(fn (Memory $memory) => $memory->isShared());

        if ($shared->count() === 2) {
            $bothSharedAt = CarbonImmutable::instance($shared->max('shared_at'));

            return $bothSharedAt->min($deadline);
        }

        if (CarbonImmutable::now()->greaterThanOrEqualTo($deadline)) {
            return $deadline;
        }

        return null;
    }

    public function hasHappened(): bool
    {
        return $this->revealedAt() !== null;
    }

    /**
     * Whether someone other than its author may read the memory: it is
     * shared, and the month has revealed. A draft is only ever its author's.
     */
    public function hasRevealed(Memory $memory): bool
    {
        return $memory->isShared() && $this->hasHappened();
    }

    /**
     * The user's memory for the month if others may read it, otherwise null.
     */
    public function revealedMemoryOf(User $user): ?Memory
    {
        $memory = $this->memories->firstWhere('user_id', $user->id);

        return $memory !== null && $this->hasRevealed($memory) ? $memory : null;
    }
}
