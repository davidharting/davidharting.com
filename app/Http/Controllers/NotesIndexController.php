<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\View\View;

class NotesIndexController extends Controller
{
    public function __invoke(): View
    {
        $query = Note::query()->orderBy('published_at', 'desc');

        if (! auth()->user()?->can('viewAny', Note::class)) {
            $query->where('visible', true);
        }

        return view('notes.index', [
            'notes' => $query->simplePaginate(1000),
        ]);
    }
}
