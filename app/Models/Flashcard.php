<?php

namespace App\Models;

use App\Casts\CardScheduleCast;
use App\Fsrs\CardSchedule;
use App\Fsrs\Rating;
use App\Fsrs\Scheduler;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

/**
 * Something David is quizzed on, prompt on the front and answer on the back,
 * plus its FSRS schedule. It points at what it quizzes (a Word today), and
 * each Word has exactly one. Reviews are its append-only log.
 *
 * @property CardSchedule $schedule
 */
class Flashcard extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schedule' => CardScheduleCast::class,
        ];
    }

    /**
     * A new flashcard for something that has never been practised, due now.
     */
    public static function unreviewed(): self
    {
        $flashcard = new self;
        $flashcard->schedule = CardSchedule::unreviewed(now());

        return $flashcard;
    }

    /**
     * What this flashcard quizzes.
     *
     * @return MorphTo<Model, $this>
     */
    public function quizzable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Flashcards due at the given moment, most overdue first.
     *
     * @param  Builder<Flashcard>  $query
     */
    #[Scope]
    protected function dueAt(Builder $query, DateTimeInterface $moment): void
    {
        $query->where('due', '<=', $moment)->orderBy('due')->orderBy('id');
    }

    /**
     * Record how well David recalled this flashcard and reschedule it. Locks
     * the row first, so two grades arriving at once are applied in turn
     * rather than one overwriting the other.
     */
    public function grade(Rating $rating, ?DateTimeInterface $reviewedAt = null, ?int $reviewDurationMs = null): Review
    {
        $reviewedAt ??= now();

        return DB::transaction(function () use ($rating, $reviewedAt, $reviewDurationMs): Review {
            $locked = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $before = $locked->schedule;

            $locked->schedule = app(Scheduler::class)->review($before, $rating, $reviewedAt);
            $locked->save();

            $review = $locked->reviews()->create([
                'rating' => $rating,
                'reviewed_at' => $reviewedAt,
                'review_duration_ms' => $reviewDurationMs,
                'schedule_before' => CardScheduleCast::columns($before),
                'schedule_after' => CardScheduleCast::columns($locked->schedule),
            ]);

            // Two steps on purpose: setRawAttributes(sync: true) syncs before it
            // clears the cast cache, so a schedule read earlier would be
            // written back over the new one.
            $this->setRawAttributes($locked->getAttributes());
            $this->syncOriginal();

            return $review;
        });
    }
}
