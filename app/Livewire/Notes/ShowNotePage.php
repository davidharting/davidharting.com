<?php

namespace App\Livewire\Notes;

use App\Models\Note;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ShowNotePage extends Component
{
    public Note $note;

    #[Computed]
    public function description(): string
    {
        $description = Str::of('');

        if ($this->note->lead) {
            $description = $description->append($this->note->lead);
            $description = $description->append("\n\n");
        }

        $description = $description->append("By David Harting.\n");
        $description = $description->append('Published on '.$this->note->published_at->format('Y F j'));

        return $description->toString();
    }

    public function render(): View
    {
        return view('livewire.notes.show-note-page');
    }

    public function mount(Note $note): void
    {
        if (! $note->visible) {
            abort(404);
        }

        $this->note = $note;
    }
}
