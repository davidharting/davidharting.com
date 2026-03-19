<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Read-only Eloquent model backed by the media_tracking_summary view.
 *
 * @property int $media_id
 * @property string $title
 * @property int|null $year
 * @property string|null $media_type
 * @property string|null $creator
 * @property string $current_status  backlog | started | finished | abandoned
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $abandoned_at
 */
class MediaTrackingSummary extends Model
{
    protected $table = 'media_tracking_summary';

    protected $primaryKey = 'media_id';

    public $timestamps = false;

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'abandoned_at' => 'datetime',
    ];
}
