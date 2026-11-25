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

    /**
     * The sort a listing uses when none is asked for: a search is a lookup,
     * so it ranks by best match.
     */
    public static function defaultFor(bool $searching): self
    {
        return $searching ? self::BestMatch : self::Recent;
    }

    /**
     * The sort a listing actually uses: the one asked for, or the default.
     * Best match ranks a search, so with nothing to rank it lists as Recent.
     */
    public static function resolve(?self $requested, bool $searching): self
    {
        $sort = $requested ?? self::defaultFor($searching);

        return $sort === self::BestMatch && ! $searching ? self::Recent : $sort;
    }

    /**
     * The sorts the words page offers as buttons, in order. Best match is
     * only offered for a search, since it ranks one.
     *
     * @return list<self>
     */
    public static function offeredOnPage(bool $searching): array
    {
        return $searching ? [self::BestMatch, self::Recent, self::AToZ] : [self::Recent, self::AToZ];
    }
}
