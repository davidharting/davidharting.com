<?php

namespace App\View\Components\Memories;

use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * One month in the list. Before the reveal it says where both memories
 * stand; after, it opens with a line of what the viewer can read.
 */
class MonthTile extends MonthForViewer
{
    /**
     * The tile shows at most this many characters of a memory.
     */
    public const SNIPPET_LENGTH = 160;

    /**
     * The start of the other person's revealed memory, or else the viewer's
     * own shared one, up to the end of its first line.
     */
    public function snippet(): ?string
    {
        if (! $this->reveal->hasHappened()) {
            return null;
        }

        $memory = $this->revealedOtherMemory() ?? ($this->viewerMemory?->isShared() ? $this->viewerMemory : null);

        if ($memory === null) {
            return null;
        }

        return Str::of($memory->body)->trim()->before("\n")->limit(self::SNIPPET_LENGTH)->toString();
    }

    public function isEmpty(): bool
    {
        return $this->viewerMemory === null && $this->otherMemory === null;
    }

    public function render(): View
    {
        return view('components.memories.month-tile');
    }
}
