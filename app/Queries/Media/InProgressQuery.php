<?php

namespace App\Queries\Media;

use App\Enum\MediaEventTypeName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InProgressQuery
{
    public function __construct() {}

    public function execute(): Collection
    {

        $query = DB::table('media')
            ->join('media_types', 'media.media_type_id', '=', 'media_types.id')
            ->join('media_events as started_events', 'media.id', '=', 'started_events.media_id')
            ->join('media_event_types', 'started_events.media_event_type_id', '=', 'media_event_types.id')
            ->join('creators', 'creators.id', '=', 'media.creator_id')
            ->where('media_event_types.name', MediaEventTypeName::STARTED);

        $query->whereNotExists(function ($subQuery) {
            $subQuery->select(DB::raw(1))
                ->from('media_events as done_events')
                ->join('media_event_types as second_event_types', 'done_events.media_event_type_id', '=', 'second_event_types.id')
                ->whereRaw('done_events.media_id = media.id')
                ->whereColumn('done_events.media_id', 'media.id')
                ->whereIn('second_event_types.name', [MediaEventTypeName::FINISHED, MediaEventTypeName::ABANDONED]);
        });

        $query->select(
            'media.title as title',
            'creators.name as creator',
            'media_types.name as type',
            'started_events.occurred_at as occurred_at',
            'media.note as note'
        );

        return $query->get();
    }
}
