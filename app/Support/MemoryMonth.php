<?php

namespace App\Support;

use App\Models\Memory;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * One month as one participant sees it: their own memory, the other
 * person's, and the reveal derived from the two. This is where the reveal
 * rules live; nothing about a reveal is stored.
 *
 * A month reveals when both have shared, or at its deadline, whichever comes
 * first. A memory shared after the reveal is readable as soon as it is shared.
 */
final readonly class MemoryMonth
{
    public function __construct(
        public Month $month,
        public User $viewer,
        public User $other,
        public ?Memory $mine,
        public ?Memory $theirs,
    ) {}

    /**
     * When the month revealed, or null while it hasn't. Answers for now
     * unless asked about another moment.
     */
    public function revealedAt(?DateTimeInterface $at = null): ?CarbonImmutable
    {
        $at = CarbonImmutable::instance($at ?? CarbonImmutable::now());
        $deadline = $this->month->revealDeadline();

        if ($this->mine?->isShared() && $this->theirs?->isShared()) {
            $bothShared = CarbonImmutable::instance($this->mine->shared_at->max($this->theirs->shared_at));

            return $bothShared->min($deadline);
        }

        return $at->greaterThanOrEqualTo($deadline) ? $deadline : null;
    }

    public function isRevealed(): bool
    {
        return $this->revealedAt() !== null;
    }

    /**
     * Whether the reveal came from the deadline rather than from both sharing.
     */
    public function revealedAtDeadline(): bool
    {
        return $this->revealedAt()?->equalTo($this->month->revealDeadline()) ?? false;
    }

    /**
     * The other person's memory, if the viewer may read it: shared, and the
     * month revealed. Their drafts are never readable.
     */
    public function readableTheirs(): ?Memory
    {
        return $this->theirs?->isShared() && $this->isRevealed() ? $this->theirs : null;
    }

    /**
     * Whether a memory of this month was shared after the month revealed.
     */
    public function sharedLate(?Memory $memory): bool
    {
        $revealedAt = $this->revealedAt();

        return $memory?->isShared() && $revealedAt !== null && $memory->shared_at->greaterThan($revealedAt);
    }

    /**
     * Nobody has started a memory for this month.
     */
    public function isEmpty(): bool
    {
        return $this->mine === null && $this->theirs === null;
    }
}
