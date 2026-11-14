<?php

namespace App\Enum;

/**
 * Orders for browsing David's word list. Each ends with an id tiebreaker so
 * pages never shuffle words between them.
 */
enum WordSort: string
{
    /** Newest first: the words he added most recently. */
    case Recent = 'recent';

    /** Alphabetical, ignoring case, like a dictionary. */
    case Alphabetical = 'az';
}
