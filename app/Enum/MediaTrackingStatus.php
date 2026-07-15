<?php

namespace App\Enum;

/**
 * The derived tracking status of a media item, as computed by the
 * media_tracking_summary view's current_status column. Unlike
 * MediaEventTypeName this includes Backlog, the status of an item
 * with no started/finished/abandoned events.
 */
enum MediaTrackingStatus: string
{
    case Backlog = 'backlog';
    case Started = 'started';
    case Finished = 'finished';
    case Abandoned = 'abandoned';
}
