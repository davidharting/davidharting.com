<?php

namespace App\Queries\Media;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Enum\MediaTypeName;

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
            ->leftJoin('media_events', 'media_events.media_id', '=', 'media.id')

            ->select(
                'media.title as title',
                'creators.name as creator',
                'media_types.name as type',
                'media.created_at as occurred_at',
                'media.note as note'
            )

            ->whereNull('media_events.id')

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
