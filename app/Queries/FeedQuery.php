<?php

namespace App\Queries;

use App\Models\Note;

class FeedQuery
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public static function execute()
    {
        return Note::where('visible', true)->orderBy('published_at', 'desc')->limit(50)->get();
    }
}
