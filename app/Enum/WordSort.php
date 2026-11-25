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

    /**
     * Only for a search: words whose spelling matches first (exact, then
     * starting with the search, then containing it), then words found only
     * in a definition, each group alphabetical. Without a search, it lists
     * the same as Recent.
     */
    case BestMatch = 'best_match';

    /**
     * The sort's name on the words page's sort buttons.
     */
    public function label(): string
    {
        return match ($this) {
            self::Recent => 'Recent',
            self::AToZ => 'A to Z',
            self::MostMissed => 'Most missed',
            self::BestMatch => 'Best match',
        };
    }
}
