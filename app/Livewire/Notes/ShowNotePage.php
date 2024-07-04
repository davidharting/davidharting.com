<?php

namespace App\Livewire\Notes;

use App\Models\Note;
use Illuminate\View\View;
use Livewire\Component;

class ShowNotePage extends Component
{
    public Note $note;

    public function render(): View
    {
        return view('livewire.notes.show-note-page');
    }

    public function mount(Note $note): void
    {
        if ($note->visible) {
            $this->note = $note;
        } else {
            $this->note = null;
        }
    }
}
