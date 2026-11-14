<?php

namespace App\Models;

use App\Fsrs\Rating;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One time David was quizzed on a flashcard: how well he recalled it, and the
 * flashcard's schedule either side, so its history can be replayed. Reviews
 * are an append-only log: write them through Flashcard::grade() and never
 * change them afterwards.
 */
class Review extends Model
{
    /**
     * Keep microseconds and the offset, so reviewed_at matches the
     * last_review it produced exactly.
     */
    protected $dateFormat = 'Y-m-d H:i:s.uP';

    protected $fillable = ['rating', 'reviewed_at', 'review_duration_ms', 'schedule_before', 'schedule_after'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => Rating::class,
            'reviewed_at' => 'immutable_datetime',
            'schedule_before' => 'array',
            'schedule_after' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Flashcard, $this>
     */
    public function flashcard(): BelongsTo
    {
        return $this->belongsTo(Flashcard::class);
    }
}
