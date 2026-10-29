<?php

namespace App\View\Memories;

use App\Models\Memory;
use App\Models\Month;
use App\Models\User;
use App\Support\CalendarMonth;
use Illuminate\Support\Facades\Gate;

/**
 * What one participant sees of a month: their own memory, the other
 * person's as far as the reveal allows, and the labels that describe both.
 * The month tile and the month page are each handed one.
 */
final readonly class MonthForViewer
{
    public ?Memory $viewerMemory;

    public ?Memory $otherMemory;

    public function __construct(
        public Month $month,
        public User $viewer,
        public User $other,
    ) {
        $this->viewerMemory = $month->memoryOf($viewer);
        $this->otherMemory = $month->memoryOf($other);
    }

    /**
     * The other person's memory once the viewer may read it, otherwise null.
     */
    public function revealedOtherMemory(): ?Memory
    {
        if ($this->otherMemory === null) {
            return null;
        }

        return Gate::forUser($this->viewer)->allows('view', $this->otherMemory) ? $this->otherMemory : null;
    }

    public function viewerShareRevealed(): bool
    {
        return $this->month->revealedByShareOf($this->viewer);
    }

    public function viewerMissed(): bool
    {
        return $this->month->missedBy($this->viewer);
    }

    /**
     * For example "Draft, started Oct 3".
     */
    public function viewerStatusLabel(): string
    {
        if ($this->viewerMemory === null) {
            return 'Not started';
        }

        if ($this->viewerMemory->isDraft()) {
            return 'Draft, started '.CalendarMonth::dayLabel($this->viewerMemory->created_at);
        }

        return 'Shared '.CalendarMonth::dayLabel($this->viewerMemory->shared_at);
    }

    /**
     * For example "Katie started on Oct 3". Before the reveal it says how far
     * the other person has got; after it, only when they shared. Null once
     * the month revealed without their memory.
     */
    public function otherStatusLabel(): ?string
    {
        $name = $this->other->displayName();
        $memory = $this->otherMemory;

        if ($this->month->hasRevealed()) {
            return $memory?->isShared() ? 'Shared '.CalendarMonth::dayLabel($memory->shared_at) : null;
        }

        if ($memory === null) {
            return "{$name} hasn't started yet";
        }

        if ($memory->isDraft()) {
            return "{$name} started on ".CalendarMonth::dayLabel($memory->created_at);
        }

        return "{$name} shared on ".CalendarMonth::dayLabel($memory->shared_at);
    }

    /**
     * For example "Revealed Oct 1." or "Share yours to read each other's."
     */
    public function revealStatusLabel(): string
    {
        $revealedAt = $this->month->revealedAt();

        if ($revealedAt !== null) {
            return 'Revealed '.CalendarMonth::dayLabel($revealedAt).'.';
        }

        $deadline = CalendarMonth::dayLabel($this->month->revealDeadline());

        if (! $this->viewerMemory?->isShared()) {
            return $this->otherMemory?->isShared()
                ? "Share yours to read each other's."
                : "You'll read each other's memories once you've both shared, or on {$deadline} at the latest.";
        }

        return "You'll read each other's memories once {$this->other->displayName()} shares, or on {$deadline} at the latest.";
    }
}
