<?php

namespace App\Queries\Media;

use App\Enum\MediaEventTypeName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LogbookQuery
{
    public function __construct() {}

    /**
     * Get the logbook items.
     */
    public function execute(): Collection
    {
        return DB::table('media_events')
            ->join('media_event_types', 'media_events.media_event_type_id', '=', 'media_event_types.id')
            ->join('media', 'media_events.media_id', '=', 'media.id')
            ->join('media_types', 'media.media_type_id', '=', 'media_types.id')
            ->leftJoin('creators', 'media.creator_id', '=', 'creators.id')

            ->select(
                'media.title as title',
                'creators.name as creator',
                'media_types.name as type',
                'media_events.occurred_at as finished_at',
                DB::raw('extract(year from media_events.occurred_at) as finished_at_year'),
                'media.note as note'
            )

            ->orderBy('media_events.occurred_at', 'desc')
            ->where('media_event_types.name', MediaEventTypeName::FINISHED)
            ->get();
    }
}
