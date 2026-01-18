<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function show(Media $media): View
    {
        // Decent amount of logic here that would ideally be in query instead e.g., sorting, extracting the event type name.
        // I want to do a refactor that unifies the various event querying and handles a virtual "added" event via query / database
        // All that said, this should be fine. The view is just for me, and we usually have 1-5 events per media.

        $media->load(['mediaType', 'creator', 'events.mediaEventType']);

        $timeline = $media->events
            ->map(fn ($e) => [
                'type' => $e->mediaEventType->name->value,
                'date' => $e->occurred_at,
                'comment' => $e->comment,
            ])
            ->prepend([
                'type' => 'added',
                'date' => $media->created_at,
                'comment' => $media->note,
            ])
            ->sortBy('date')
            ->values();

        return view('media.show', [
            'media' => $media,
            'timeline' => $timeline,
        ]);
    }
}
