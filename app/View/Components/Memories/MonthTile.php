<?php

namespace App\View\Components\Memories;

use App\View\Memories\MonthForViewer;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * One month in the list. Before the reveal it says where both memories
 * stand; after, it opens with a line of what the viewer can read.
 */
class MonthTile extends Component
{
    /**
     * The tile shows at most this many characters of a memory.
     */
    public const SNIPPET_LENGTH = 160;

    public function __construct(public MonthForViewer $monthForViewer) {}

    /**
     * The start of the other person's revealed memory, or else the viewer's
     * own, up to the end of its first line. Null before the reveal.
     */
    public function snippet(): ?string
    {
        $viewerMemory = $this->monthForViewer->viewerMemory;
        $memory = $this->monthForViewer->revealedOtherMemory()
            ?? ($viewerMemory !== null && $this->monthForViewer->month->isRevealed($viewerMemory) ? $viewerMemory : null);

        if ($memory === null) {
            return null;
        }

        return Str::of($memory->body)->trim()->before("\n")->limit(self::SNIPPET_LENGTH)->toString();
    }

    public function isEmpty(): bool
    {
        return $this->monthForViewer->viewerMemory === null && $this->monthForViewer->otherMemory === null;
    }

    public function render(): View
    {
        return view('components.memories.month-tile');
    }
}
