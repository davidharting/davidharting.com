<?php

namespace App\Livewire\Notes;

use App\Models\Note;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class NotesIndexPage extends Component
{
    use WithPagination;

    public function render(): View|Factory
    {
        return view('livewire.notes.notes-index-page', [
            'notes' => Note::where('visible', true)->orderBy('published_at', 'desc')->simplePaginate(1000),
        ]);
    }
}
