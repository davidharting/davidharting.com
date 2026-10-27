<?php

namespace App\Livewire\Memories;

use App\Models\MemoryParticipant;
use App\Models\Month;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The top of the memories list: its title, and the "How it works" lines
 * that explain the premise. They open as a card under the title until the
 * viewer closes it or their first month reveals, and from then on wait
 * behind a ? beside the title.
 */
class ListHeading extends Component
{
    /**
     * Whether any month in the list has revealed. The list already knows, so
     * it is handed in rather than asked again.
     */
    #[Locked]
    public bool $anyMonthRevealed;

    /**
     * Every request, the first render and each update alike, is a participant's.
     */
    public function boot(): void
    {
        $this->authorize('viewAny', Month::class);
    }

    public function mount(bool $anyMonthRevealed): void
    {
        $this->anyMonthRevealed = $anyMonthRevealed;
    }

    public function dismiss(): void
    {
        $this->participant()->dismissHowItWorks();
    }

    #[Computed]
    public function showsCard(): bool
    {
        return ! $this->anyMonthRevealed && ! $this->participant()->hasDismissedHowItWorks();
    }

    public function render(): View
    {
        return view('livewire.memories.list-heading');
    }

    private function participant(): MemoryParticipant
    {
        return auth()->user()->memoryParticipant;
    }
}
