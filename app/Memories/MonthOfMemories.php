<?php

namespace App\Memories;

use App\Models\Memory;
use App\Models\User;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * One month as one participant sees it: their own memory, the other
 * person's, and the reveal derived from the two. This is where the reveal
 * rules live; nothing about a reveal is stored.
 *
 * A month reveals when both have shared, or at its deadline, whichever comes
 * first. A memory shared after the reveal is readable as soon as it is shared.
 */
final readonly class MonthOfMemories
{
    public function __construct(
        public Month $month,
        public User $viewer,
        public User $other,
        public ?Memory $viewerMemory,
        public ?Memory $otherMemory,
    ) {}

    /**
     * When the month revealed, or null while it hasn't.
     */
    public function revealedAt(): ?CarbonImmutable
    {
        $deadline = $this->month->revealDeadline();

        if ($this->viewerMemory?->isShared() && $this->otherMemory?->isShared()) {
            $bothShared = CarbonImmutable::instance($this->viewerMemory->shared_at->max($this->otherMemory->shared_at));

            return $bothShared->min($deadline);
        }

        return CarbonImmutable::now()->greaterThanOrEqualTo($deadline) ? $deadline : null;
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
    public function readableOtherMemory(): ?Memory
    {
        return $this->otherMemory?->isShared() && $this->isRevealed() ? $this->otherMemory : null;
    }

    /**
     * The first line of what the viewer can read once the month has revealed:
     * the other person's memory, or else the viewer's own shared one.
     */
    public function snippet(): ?string
    {
        if (! $this->isRevealed()) {
            return null;
        }

        $memory = $this->readableOtherMemory() ?? ($this->viewerMemory?->isShared() ? $this->viewerMemory : null);

        return $memory === null ? null : Str::of($memory->body)->trim()->before("\n")->toString();
    }

    /**
     * Where the other person's memory stands, as far as the viewer may know,
     * or null once the month has revealed without them sharing.
     */
    public function otherMemoryStatus(): ?string
    {
        $name = $this->other->displayName();
        $otherMemory = $this->otherMemory;

        if ($this->isRevealed()) {
            return $otherMemory?->isShared() ? 'Shared '.Month::dayLabel($otherMemory->shared_at) : null;
        }

        return match (true) {
            $otherMemory === null => "{$name} hasn't started yet",
            $otherMemory->isDraft() => "{$name} started on ".Month::dayLabel($otherMemory->created_at),
            default => "{$name} shared on ".Month::dayLabel($otherMemory->shared_at),
        };
    }

    /**
     * When the month revealed and why, or what the reveal is waiting on.
     */
    public function revealStatus(): string
    {
        $revealedAt = $this->revealedAt();

        if ($revealedAt !== null) {
            $why = $this->revealedAtDeadline() ? 'at the deadline' : "when you'd both shared";

            return 'Revealed '.Month::dayLabel($revealedAt).", {$why}.";
        }

        $deadline = Month::dayLabel($this->month->revealDeadline());

        return match (true) {
            $this->otherMemory?->isShared() && ! $this->viewerMemory?->isShared() => 'Share yours to reveal both.',
            (bool) $this->viewerMemory?->isShared() => "Reveals when {$this->other->displayName()} shares, or on {$deadline} at the latest.",
            default => "Reveals when you've both shared, or on {$deadline} at the latest.",
        };
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
        return $this->viewerMemory === null && $this->otherMemory === null;
    }
}
