<?php

namespace App\View\Components\Memories;

use App\Models\Memory;
use App\Models\MemoryPhoto;
use App\Support\CalendarMonth;
use App\View\Memories\MonthForViewer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * One month, one person per page: the other participant's memory, or the
 * viewer's own when showing theirs, with links to the neighbouring months.
 */
class MonthPage extends Component
{
    public function __construct(
        public MonthForViewer $monthForViewer,
        public bool $showingYours,
        public ?CalendarMonth $previous,
        public ?CalendarMonth $next,
    ) {}

    /**
     * The memory this page shows, if there is one the viewer may read.
     */
    public function shownMemory(): ?Memory
    {
        return $this->showingYours ? $this->monthForViewer->viewerMemory : $this->monthForViewer->revealedOtherMemory();
    }

    /**
     * The shown memory's photos in order. Others see only ready photos; on
     * the viewer's own page, photos still processing or that failed appear
     * too, so the author knows they are there.
     *
     * @return Collection<int, MemoryPhoto>
     */
    public function photos(): Collection
    {
        $photos = $this->shownMemory()?->photos ?? new Collection;

        if ($this->showingYours) {
            return $photos;
        }

        return $photos->filter(fn (MemoryPhoto $photo) => $photo->isReady())->values();
    }

    /**
     * The quiet line above the memory, or null when there is nothing to say.
     * It shows the viewer's own draft, and how far the other person has got
     * before the reveal; never when anything was shared or revealed.
     */
    public function statusLabel(): ?string
    {
        if ($this->showingYours) {
            return $this->monthForViewer->viewerMemory?->isDraft() ? $this->monthForViewer->viewerStatusLabel() : null;
        }

        return $this->monthForViewer->month->hasRevealed() ? null : $this->monthForViewer->otherStatusLabel();
    }

    /**
     * How a link to a neighbouring month reads: just its name in the same
     * year, and with the year when it is in another one.
     */
    public function neighbourLabel(CalendarMonth $neighbour): string
    {
        return $neighbour->year === $this->monthForViewer->month->calendarMonth->year ? $neighbour->name() : $neighbour->label();
    }

    public function render(): View
    {
        return view('components.memories.month-page');
    }
}
