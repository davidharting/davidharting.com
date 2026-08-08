<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Read-only Eloquent model backed by the media_tracking_summary view.
 *
 * The last three columns — `note`, `full_text`, `history` — are three shapes of
 * the same underlying free text: the item's note verbatim, everything it has
 * accumulated as one searchable markdown string, and the full event timeline as
 * structured data. All three are admin-only (`MediaPolicy::seeNote`), and that
 * is enforced in the application, so anything serving an unauthorized request
 * must select columns explicitly rather than relying on `select *` — see
 * `App\Queries\Media\SearchMediaQuery::COLUMNS`.
 *
 * @property int $media_id
 * @property int|null $creator_id
 * @property string $title
 * @property int|null $year
 * @property string $media_type
 * @property string|null $creator
 * @property string $current_status backlog | started | finished | abandoned
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $abandoned_at
 * @property string|null $note ADMIN-ONLY. See the class docblock.
 * @property string|null $full_text ADMIN-ONLY. See the class docblock.
 * @property list<array{type: string, occurred_at: string, comment: string|null}> $history ADMIN-ONLY. See the class docblock.
 */
class MediaTrackingSummary extends Model
{
    protected $table = 'media_tracking_summary';

    protected $primaryKey = 'media_id';

    public $timestamps = false;

    // This model is backed by a PostgreSQL view — it is read-only.
    protected $guarded = ['*'];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'abandoned_at' => 'datetime',
        'history' => 'array',
    ];
}
