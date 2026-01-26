<?php

namespace App\Http\Controllers\Notes;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShowNoteController extends Controller
{
    public function __invoke(Note $note): View
    {
        return view('notes.show', [
            'note' => $note,
            'description' => $this->description($note),
        ]);
    }

    private function description(Note $note): string
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
