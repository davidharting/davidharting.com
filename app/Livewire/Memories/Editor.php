<?php

namespace App\Livewire\Memories;

use App\Memories\MemoryChangedElsewhere;
use App\Memories\MemoryWriter;
use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Queries\ListOpenMonthsQuery;
use App\Support\Month;
use App\View\Memories\MonthForViewer;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The viewer's own memory for one month, on its own page. The text saves
 * as they type and is shared only through the Share sheet.
 *
 * The editor remembers the `lock_version` it last saw. When another device
 * saved in the meantime, it stops saving and asks which version to keep.
 */
class Editor extends Component
{
    /**
     * The month as `Y-m`.
     */
    #[Locked]
    public string $month;

    public string $body = '';

    /**
     * The version of the memory this editor last loaded or saved, or null
     * while it hasn't seen one.
     */
    #[Locked]
    public ?int $lockVersion = null;

    /**
     * Another device saved since this editor last did, so saving is paused.
     */
    #[Locked]
    public bool $changedElsewhere = false;

    /**
     * Shows the screen that follows sharing.
     */
    #[Locked]
    public bool $justShared = false;

    public function boot(): void
    {
        $this->authorize('viewMonth', Memory::class);
    }

    public function mount(string $month): void
    {
        $this->month = $month;
        abort_unless((new ListOpenMonthsQuery)->contains($this->monthValue()), 404);

        $this->load();
        $this->authorizeWriting();
    }

    public function updatedBody(): void
    {
        if ($this->changedElsewhere) {
            return;
        }

        $this->save();
    }

    public function save(): void
    {
        $this->write(fn (MemoryWriter $writer) => $writer->save($this->body, $this->lockVersion));
    }

    public function share(): void
    {
        $shared = $this->write(fn (MemoryWriter $writer) => $writer->share($this->body, $this->lockVersion));

        $this->justShared = $shared !== null;
    }

    /**
     * Resolve a conflict by taking the version saved on the other device.
     */
    public function useTheirs(): void
    {
        $this->load();
    }

    /**
     * Resolve a conflict by saving this editor's text over the other device's.
     */
    public function keepMine(): void
    {
        if (! $this->changedElsewhere) {
            return;
        }

        $this->lockVersion = $this->monthForViewer->viewerMemory?->lock_version;
        $this->changedElsewhere = false;

        $this->save();
    }

    public function keepEditing(): void
    {
        $this->justShared = false;
    }

    #[Computed]
    public function monthForViewer(): MonthForViewer
    {
        $viewer = auth()->user();
        $month = $this->monthValue();

        return MonthForViewer::fromMemories(
            $month,
            $viewer,
            MemoryParticipant::otherThan($viewer),
            Memory::query()->where('month', $month->toDateString())->get(),
        );
    }

    protected function isShared(): bool
    {
        return (bool) $this->monthForViewer->viewerMemory?->isShared();
    }

    /**
     * The status in the bar above the keyboard while nothing is in flight,
     * for example "Shared · Saved". Null before the first save.
     */
    protected function saveStatusLabel(): ?string
    {
        if ($this->changedElsewhere) {
            return 'Changed on another device';
        }

        if ($this->lockVersion === null) {
            return null;
        }

        return $this->isShared() ? 'Shared · Saved' : 'Saved';
    }

    /**
     * The one line above the text about where the month stands, for example
     * "Katie has shared. Share yours to reveal both."
     */
    protected function standingLabel(): string
    {
        $monthForViewer = $this->monthForViewer;
        $other = $monthForViewer->other->displayName();
        $otherMemory = $monthForViewer->otherMemory;
        $revealed = $monthForViewer->reveal->hasHappened();

        if ($this->isShared()) {
            $shared = 'Shared '.Month::dayLabel($monthForViewer->viewerMemory->shared_at).'. Edits still save as you type, and ';

            return $shared.($revealed
                ? "{$other} sees them."
                : "{$other} sees them once {$this->monthValue()->name()} is revealed.");
        }

        if ($revealed) {
            return "{$this->monthValue()->name()} has revealed, so {$other} can read yours as soon as you share it.";
        }

        if ($otherMemory === null) {
            return "{$other} hasn't started yet.";
        }

        if ($otherMemory->isDraft()) {
            return "{$other} started on ".Month::dayLabel($otherMemory->created_at)." and hasn't shared yet.";
        }

        return "{$other} has shared. Share yours to reveal both.";
    }

    /**
     * The Share sheet's first fact: when the other person can read it.
     */
    protected function whenTheyCanReadLabel(): string
    {
        $monthForViewer = $this->monthForViewer;
        $other = $monthForViewer->other->displayName();
        $name = $this->monthValue()->name();

        if ($monthForViewer->reveal->hasHappened()) {
            return "{$name} has already revealed, so {$other} can read it right away.";
        }

        if ($monthForViewer->otherMemory?->isShared()) {
            return "{$other} has shared, so {$name} reveals as soon as you do.";
        }

        return "{$other} can read it once you've both shared, or on ".Month::dayLabel($this->monthValue()->revealDeadline()).'.';
    }

    public function render(): View
    {
        return view('livewire.memories.editor');
    }

    protected function monthValue(): Month
    {
        return Month::parseYearMonth($this->month);
    }

    /**
     * Writing means updating the viewer's memory, or creating it if there
     * isn't one yet.
     */
    private function authorizeWriting(): void
    {
        $memory = $this->monthForViewer->viewerMemory;

        if ($memory === null) {
            $this->authorize('create', Memory::class);

            return;
        }

        $this->authorize('update', $memory);
    }

    private function load(): void
    {
        unset($this->monthForViewer);
        $memory = $this->monthForViewer->viewerMemory;

        $this->body = $memory->body ?? '';
        $this->lockVersion = $memory?->lock_version;
        $this->changedElsewhere = false;
    }

    /**
     * Run a write, keeping the version it saved, or pause saving if another
     * device got there first. A write that saved tells the browser with
     * `memory-saved`, so the status can briefly show the save landing.
     *
     * @param  callable(MemoryWriter): ?Memory  $write
     */
    private function write(callable $write): ?Memory
    {
        $this->authorizeWriting();

        try {
            $memory = $write(new MemoryWriter(auth()->user(), $this->monthValue()));
        } catch (MemoryChangedElsewhere) {
            $this->changedElsewhere = true;

            return null;
        }

        unset($this->monthForViewer);
        $this->resetErrorBag('body');

        if ($memory !== null) {
            $this->lockVersion = $memory->lock_version;
            $this->dispatch('memory-saved');
        }

        return $memory;
    }
}
