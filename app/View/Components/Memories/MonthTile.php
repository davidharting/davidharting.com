<?php

namespace App\View\Components\Memories;

use App\Models\Memory;
use App\Models\MemoryPhoto;
use App\View\Memories\MonthForViewer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * One month in the list. Before the reveal it says where both memories
 * stand; after, it leads with up to two thumbnails and a line of what the
 * viewer can read. A month the viewer missed stays quiet and links to the
 * editor.
 */
class MonthTile extends Component
{
    /**
     * The tile shows at most this many characters of a memory.
     */
    public const SNIPPET_LENGTH = 160;

    /**
     * The tile leads with at most this many thumbnails.
     */
    public const THUMBNAIL_COUNT = 2;

    public function __construct(public MonthForViewer $monthForViewer) {}

    /**
     * The start of the first revealed memory, up to the end of its first
     * line. Null before the reveal.
     */
    public function snippet(): ?string
    {
        $memory = $this->revealedMemories()->first();

        if ($memory === null) {
            return null;
        }

        return Str::of($memory->body)->trim()->before("\n")->limit(self::SNIPPET_LENGTH)->toString();
    }

    /**
     * Each revealed memory's first ready photo, the other person's first.
     * When one memory has no ready photos, the other's next photos fill the
     * remaining slots. None before the reveal.
     *
     * @return Collection<int, MemoryPhoto>
     */
    public function thumbnails(): Collection
    {
        $readyPhotosByMemory = $this->revealedMemories()
            ->map(fn (Memory $memory) => $memory->photos->filter(fn (MemoryPhoto $photo) => $photo->isReady())->values());

        $firstOfEach = $readyPhotosByMemory->map(fn (Collection $photos) => $photos->first())->filter();
        $theRest = $readyPhotosByMemory->flatMap(fn (Collection $photos) => $photos->slice(1));

        return $firstOfEach->concat($theRest)
            ->take(self::THUMBNAIL_COUNT)
            ->values();
    }

    /**
     * The display name of whoever wrote the memory the photo belongs to.
     */
    public function authorNameOf(MemoryPhoto $photo): string
    {
        $author = $photo->memory_id === $this->monthForViewer->viewerMemory?->id
            ? $this->monthForViewer->viewer
            : $this->monthForViewer->other;

        return $author->displayName();
    }

    /**
     * The other person's revealed memory, then the viewer's own once the
     * month has revealed it.
     *
     * @return Collection<int, Memory>
     */
    private function revealedMemories(): Collection
    {
        $viewerMemory = $this->monthForViewer->viewerMemory;

        return collect([
            $this->monthForViewer->revealedOtherMemory(),
            $viewerMemory !== null && $this->monthForViewer->month->isRevealed($viewerMemory) ? $viewerMemory : null,
        ])->filter()->values();
    }

    /**
     * Whether a missed month has nothing for the viewer to read, so the tile
     * shrinks to a single quiet line.
     */
    public function isQuietLine(): bool
    {
        return $this->monthForViewer->viewerMissed() && $this->revealedMemories()->isEmpty();
    }

    public function render(): View
    {
        return view('components.memories.month-tile');
    }
}
