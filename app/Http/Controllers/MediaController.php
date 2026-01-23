<?php

namespace App\Http\Controllers;

use App\Enum\MediaTypeName;
use App\Models\Media;
use App\Queries\Media\BacklogQuery;
use App\Queries\Media\InProgressQuery;
use App\Queries\Media\LogbookQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(Request $request): View
    {
        $list = $request->get('list', 'finished');
        $year = $request->get('year', '');
        $type = $request->get('type', '');

        $yearInt = $year ? (int) $year : null;
        $typeEnum = $type ? MediaTypeName::from($type) : null;

        $items = match ($list) {
            'backlog' => (new BacklogQuery($yearInt, $typeEnum))->execute(),
            'in-progress' => (new InProgressQuery)->execute(),
            default => (new LogbookQuery($yearInt, $typeEnum))->execute(),
        };

        $years = (new LogbookQuery)->years();
        $mediaTypes = MediaTypeName::cases();
        $disableFilters = $list === 'in-progress';

        return view('media.index', compact('items', 'years', 'mediaTypes', 'list', 'year', 'type', 'disableFilters'));
    }

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
