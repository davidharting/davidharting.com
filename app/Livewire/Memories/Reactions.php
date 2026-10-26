<?php

namespace App\Livewire\Memories;

use App\Memories\MemoryReactionWriter;
use App\Models\Memory;
use App\Models\MemoryReaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The row of reactions under a revealed memory on the month page. The other
 * participant adds and removes theirs here; the author sees the same row
 * read-only.
 */
class Reactions extends Component
{
    #[Locked]
    public Memory $memory;

    /**
     * The id of the reaction removed last, which Undo puts back.
     */
    #[Locked]
    public ?int $removedId = null;

    public function mount(Memory $memory): void
    {
        $this->authorize('view', $memory);

        $this->memory = $memory;
    }

    /**
     * Whoever may no longer read the memory may not refresh the row either.
     */
    public function hydrate(): void
    {
        $this->authorize('view', $this->memory);
    }

    public function add(string $emoji): void
    {
        $this->authorize('react', $this->memory);

        $this->writer()->add($emoji);
    }

    /**
     * Returns whether there was a reaction to remove, so the browser only
     * offers Undo when there is something to undo.
     */
    public function remove(string $emoji): bool
    {
        $this->authorize('react', $this->memory);

        $this->removedId = $this->writer()->remove($emoji)?->id;

        return $this->removedId !== null;
    }

    public function undo(): void
    {
        $this->authorize('react', $this->memory);

        if ($this->removedId === null) {
            return;
        }

        $removedId = $this->removedId;
        $this->removedId = null;

        $this->writer()->restore($removedId);
    }

    #[Computed]
    public function canReact(): bool
    {
        return Gate::allows('react', $this->memory);
    }

    /**
     * @return Collection<int, MemoryReaction>
     */
    #[Computed]
    public function reactions(): Collection
    {
        return $this->memory->reactions()->with('user')->get();
    }

    #[Computed]
    public function hasRoom(): bool
    {
        return $this->reactions->where('user_id', auth()->id())->count() < MemoryReactionWriter::MAX_PER_PERSON;
    }

    public function render(): View
    {
        return view('livewire.memories.reactions');
    }

    private function writer(): MemoryReactionWriter
    {
        return new MemoryReactionWriter(auth()->user(), $this->memory);
    }
}
