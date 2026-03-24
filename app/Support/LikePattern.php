<?php

namespace App\Support;

class LikePattern
{
    /**
     * Escape LIKE/ILIKE wildcard characters so a value is matched literally.
     * Without this, '%' and '_' in user input would be treated as wildcards.
     */
    public static function escape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
