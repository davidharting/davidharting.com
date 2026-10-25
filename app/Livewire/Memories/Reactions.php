<?php

namespace App\Livewire\Memories;

use App\Memories\MemoryReactionWriter;
use App\Models\Memory;
use App\Models\MemoryReaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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
     * The reaction removed last, kept so Undo can put it back in its place.
     *
     * @var array{emoji: string, addedAt: string}|null
     */
    #[Locked]
    public ?array $removed = null;

    public function mount(Memory $memory): void
    {
        $this->authorize('view', $memory);

        $this->memory = $memory;
    }

    /**
     * Anything that isn't one emoji, or a sixth, is ignored: the browser
     * only sends what it believes is one, and hides + at the limit.
     */
    public function add(string $emoji): void
    {
        $this->authorize('react', $this->memory);

        try {
            $this->writer()->add($emoji);
        } catch (ValidationException) {
            return;
        }
    }

    public function remove(string $emoji): void
    {
        $this->authorize('react', $this->memory);

        $reaction = $this->writer()->remove($emoji);

        if ($reaction !== null) {
            $this->removed = ['emoji' => $reaction->emoji, 'addedAt' => $reaction->created_at->toIso8601String()];
        }
    }

    public function undo(): void
    {
        $this->authorize('react', $this->memory);

        if ($this->removed === null) {
            return;
        }

        try {
            $this->writer()->restore($this->removed['emoji'], CarbonImmutable::parse($this->removed['addedAt']));
        } catch (ValidationException) {
            return;
        } finally {
            $this->removed = null;
        }
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
