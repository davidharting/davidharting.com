<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Head\Enums\OgType;
use Laravel\Head\Facades\Head;
use Laravel\Head\Facades\Schema;

class NoteController extends Controller
{
    public function show(Note $note): View
    {
        $title = $note->title ?? $note->publicationDate();

        Head::title($title)
            ->description($this->description($note))
            ->og(type: OgType::Article, title: $title)
            ->twitter(title: $title)
            ->meta('article:published_time', $note->published_at->toIso8601String())
            ->unless($note->visible, fn ($head) => $head->hiddenFromRobots())
            ->schema(Schema::blogPosting()
                ->headline($title)
                ->description($this->description($note))
                ->author(Schema::person()->name('David Harting')->url(route('home')))
                ->publishedAt($note->published_at)
                ->modifiedAt($note->updated_at))
            ->schema(Schema::breadcrumbs()->items([
                'Home' => route('home'),
                'Notes' => route('notes.index'),
                $title => route('notes.show', $note),
            ]));

        return view('notes.show', [
            'note' => $note,
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
