<?php

namespace App\Enum;

/**
 * Sort orders for media library queries backed by media_tracking_summary.
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

    /** Release year, most recent first. */
    case Year = 'year';
}
