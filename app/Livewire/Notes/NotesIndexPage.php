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
        $query = Note::query()->orderBy('published_at', 'desc');

        if (! auth()->user()?->can('viewAny', Note::class)) {
            $query->where('visible', true);
        }

        return view('livewire.notes.notes-index-page', [
            'notes' => $query->simplePaginate(1000),
        ]);
    }
}
