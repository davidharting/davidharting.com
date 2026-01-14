<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function show(Media $media): View
    {
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
                'comment' => null,
            ])
            ->sortBy('date')
            ->values();

        return view('media.show', [
            'media' => $media,
            'timeline' => $timeline,
        ]);
    }
}
