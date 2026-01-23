<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Support\Str;

class NotesController extends Controller
{
    public function index()
    {
        $query = Note::query()->orderBy('published_at', 'desc');

        if (! auth()->user()?->can('viewAny', Note::class)) {
            $query->where('visible', true);
        }

        $notes = $query->simplePaginate(1000);

        return view('notes.index', compact('notes'));
    }

    public function show(Note $note)
    {
        $this->authorize('view', $note);

        $description = $this->generateDescription($note);

        return view('notes.show', compact('note', 'description'));
    }

    private function generateDescription(Note $note): string
    {
        $description = Str::of('');

        if ($note->lead) {
            $description = $description->append($note->lead);
            $description = $description->append("\n\n");
        }

        $description = $description->append("By David Harting.\n");
        $description = $description->append('Published on '.$note->published_at->format('Y F j'));

        return $description->toString();
    }
}
