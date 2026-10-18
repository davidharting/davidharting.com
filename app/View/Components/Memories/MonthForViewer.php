<?php

namespace App\View\Components\Memories;

use App\Memories\Reveal;
use App\Models\Memory;
use App\Models\User;
use App\Support\Month;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

/**
 * What one participant sees of a month: their own memory, the other
 * person's as far as the reveal allows, and the labels that describe both.
 * The month tile and the month page build on it.
 */
abstract class MonthForViewer extends Component
{
    public readonly ?Memory $viewerMemory;

    public readonly ?Memory $otherMemory;

    public readonly Reveal $reveal;

    /**
     * @param  Collection<int, Memory>  $memories  Every memory written for the month.
     */
    public function __construct(
        public Month $month,
        public User $viewer,
        public User $other,
        Collection $memories,
    ) {
        $this->viewerMemory = $memories->firstWhere('user_id', $viewer->id);
        $this->otherMemory = $memories->firstWhere('user_id', $other->id);
        $this->reveal = new Reveal($month, $memories);
    }

    /**
     * The other person's memory once the viewer may read it, otherwise null.
     */
    public function revealedOtherMemory(): ?Memory
    {
        if ($this->otherMemory === null || ! $this->reveal->hasRevealed($this->otherMemory)) {
            return null;
        }

        return $this->otherMemory;
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
            return 'Draft, started '.Month::dayLabel($this->viewerMemory->created_at);
        }

        return 'Shared '.Month::dayLabel($this->viewerMemory->shared_at);
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

        if ($this->reveal->hasHappened()) {
            return $memory?->isShared() ? 'Shared '.Month::dayLabel($memory->shared_at) : null;
        }

        if ($memory === null) {
            return "{$name} hasn't started yet";
        }

        if ($memory->isDraft()) {
            return "{$name} started on ".Month::dayLabel($memory->created_at);
        }

        return "{$name} shared on ".Month::dayLabel($memory->shared_at);
    }

    /**
     * For example "Revealed Oct 1." or "Share yours to reveal both."
     */
    public function revealStatusLabel(): string
    {
        $revealedAt = $this->reveal->revealedAt();

        if ($revealedAt !== null) {
            return 'Revealed '.Month::dayLabel($revealedAt).'.';
        }

        $deadline = Month::dayLabel($this->month->revealDeadline());

        if (! $this->viewerMemory?->isShared()) {
            return $this->otherMemory?->isShared()
                ? 'Share yours to reveal both.'
                : "Reveals when you've both shared, or on {$deadline} at the latest.";
        }

        return "Reveals when {$this->other->displayName()} shares, or on {$deadline} at the latest.";
    }
}
