<?php

namespace App\Queries\Media;

use App\Enum\MediaEventTypeName;
use App\Enum\MediaTypeName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BacklogQuery
{
    public function __construct(
        public ?int $year = null,
        public ?MediaTypeName $type = null
    ) {}

    public function execute(): Collection
    {
        $query = DB::table('media')
            ->join('media_types', 'media.media_type_id', '=', 'media_types.id')
            ->leftJoin('creators', 'media.creator_id', '=', 'creators.id')

            ->select(
                'media.id as id',
                'media.title as title',
                'creators.name as creator',
                'media_types.name as type',
                'media.created_at as occurred_at',
                'media.note as note'
            )

            // No started, finished, or abandoned events (comment events are ignored)
            ->whereNotExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('media_events')
                    ->join('media_event_types', 'media_events.media_event_type_id', '=', 'media_event_types.id')
                    ->whereColumn('media_events.media_id', 'media.id')
                    ->whereIn('media_event_types.name', [
                        MediaEventTypeName::STARTED,
                        MediaEventTypeName::FINISHED,
                        MediaEventTypeName::ABANDONED,
                    ]);
            })

            ->orderBy('media.created_at', 'desc');

        if ($this->year !== null) {
            $query->whereYear('media.created_at', $this->year);
        }

        if ($this->type !== null) {
            $query->where('media_types.name', $this->type);
        }

        return $query->get();
    }
}
