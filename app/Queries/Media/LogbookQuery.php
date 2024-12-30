<?php

namespace App\Queries\Media;

use App\Enum\MediaEventTypeName;
use App\Enum\MediaTypeName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LogbookQuery
{
    public function __construct(
        public ?int $year = null,
        public ?MediaTypeName $type = null
    ) {}

    /**
     * Get the logbook items.
     */
    public function execute(): Collection
    {
        $query = DB::table('media_events')
            ->join('media_event_types', 'media_events.media_event_type_id', '=', 'media_event_types.id')
            ->join('media', 'media_events.media_id', '=', 'media.id')
            ->join('media_types', 'media.media_type_id', '=', 'media_types.id')
            ->leftJoin('creators', 'media.creator_id', '=', 'creators.id')

            ->select(
                'media.title as title',
                'creators.name as creator',
                'media_types.name as type',
                'media_events.occurred_at as occurred_at',
                'media.note as note'
            )

            ->orderBy('media_events.occurred_at', 'desc')
            ->where('media_event_types.name', MediaEventTypeName::FINISHED);

        if ($this->year !== null) {
            $query->whereYear('media_events.occurred_at', $this->year);
        }

        if ($this->type !== null) {
            // TODO: MediaType filter currently untested
            $query->where('media_types.name', $this->type);
        }

        return $query->get();
    }

    /**
     * @returns int[]
     */
    public function years(): array
    {
        return DB::table('media_event_types')
            ->join('media_events', 'media_events.media_event_type_id', 'media_event_types.id')
            ->where('media_event_types.name', MediaEventTypeName::FINISHED)
            ->selectRaw('distinct extract(year from media_events.occurred_at) as year')
            ->orderBy('year', 'desc')
            ->get()
            ->pluck('year')
            ->toArray();
    }
}
