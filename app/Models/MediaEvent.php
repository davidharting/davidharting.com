<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'media_id',
        'media_event_type_id',
        'occurred_at',
        'comment',
    ];

    protected $casts = [
        'occurred_at' => 'datetime:Y-m-d H:i:s.uO',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function mediaEventType(): BelongsTo
    {
        return $this->belongsTo(MediaEventType::class);
    }
}
