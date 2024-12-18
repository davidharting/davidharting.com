<?php

namespace App\Queries\Media;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BacklogQuery
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function execute(): Collection
    {
        return DB::table('media')
            ->join('media_types', 'media.media_type_id', '=', 'media_types.id')
            ->leftJoin('creators', 'media.creator_id', '=', 'creators.id')
            ->leftJoin('media_events', 'media_events.media_id', '=', 'media.id')

            ->select(
                'media.title as title',
                'creators.name as creator',
                'media_types.name as type',
                'media.created_at as added_at',
            )

            ->whereNull('media_events.id')

            ->orderBy('media.created_at', 'desc')
            ->get();
    }
}
