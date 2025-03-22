<?php

namespace App\Queries;

use Illuminate\Support\Collection;

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
        return Collection::empty();
    }
}
