<?php

namespace App\Enum;

/**
 * Sort orders for David's vocabulary list.
 *
 * Every sort ends with an id tiebreaker, so pages of results never overlap.
 */
enum WordSort: string
{
    /** Most recently added first. */
    case Recent = 'recent';

    /** Alphabetical by word, ignoring case. */
    case AToZ = 'a_to_z';

    /** Missed most often in practice first, then alphabetical. */
    case MostMissed = 'most_missed';
}
