<?php

namespace App\Enum;

/**
 * Sort orders for media library queries backed by media_tracking_summary.
 *
 * Every sort ends with a media_id (library insertion order) tiebreaker, so
 * ordering is fully deterministic: items a sort cannot rank — e.g.
 * never-finished items under RecentlyFinished — come last, oldest first.
 */
enum MediaSort: string
{
    /** Most recently finished first; never-finished items last. */
    case RecentlyFinished = 'recently_finished';

    /** Most recently started first; never-started items last. */
    case RecentlyStarted = 'recently_started';

    /**
     * Most recently added to the library first. The view does not expose
     * created_at, so this orders by media_id, which increases monotonically.
     */
    case RecentlyAdded = 'recently_added';

    /** Alphabetical by title. */
    case Title = 'title';

    /** Alphabetical by creator name. */
    case Creator = 'creator';

    /** Release year, most recent first. */
    case Year = 'year';
}
