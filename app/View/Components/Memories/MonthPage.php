<?php

namespace App\View\Components\Memories;

use App\Models\Memory;
use App\Models\User;
use App\Support\Month;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * One month, one person per page: the other participant's memory, or the
 * viewer's own when showing theirs, with links to the neighbouring months.
 */
class MonthPage extends MonthForViewer
{
    /**
     * @param  Collection<int, Memory>  $memories  Every memory written for the month.
     */
    public function __construct(
        Month $month,
        User $viewer,
        User $other,
        Collection $memories,
        public bool $showingYours,
        public ?Month $older,
        public ?Month $newer,
    ) {
        parent::__construct($month, $viewer, $other, $memories);
    }

    /**
     * The memory this page shows, if there is one the viewer may read.
     */
    public function shownMemory(): ?Memory
    {
        return $this->showingYours ? $this->viewerMemory : $this->revealedOtherMemory();
    }

    /**
     * The quiet line above the memory, or null when there is nothing to say.
     */
    public function statusLabel(): ?string
    {
        if ($this->showingYours) {
            return $this->viewerMemory === null ? null : $this->viewerStatusLabel();
        }

        return $this->otherStatusLabel();
    }

    public function render(): View
    {
        return view('components.memories.month-page');
    }
}
