<?php

namespace App\View\Components\Memories;

use App\Models\Memory;
use App\Support\CalendarMonth;
use App\View\Memories\MonthForViewer;
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
     * The quiet line above the memory, or null when there is nothing to say.
     */
    public function statusLabel(): ?string
    {
        if ($this->showingYours) {
            return $this->monthForViewer->viewerMemory === null ? null : $this->monthForViewer->viewerStatusLabel();
        }

        return $this->monthForViewer->otherStatusLabel();
    }

    public function render(): View
    {
        return view('components.memories.month-page');
    }
}
