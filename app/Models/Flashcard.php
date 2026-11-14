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
    public static function newUnreviewed(): self
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
     * Flashcards in the order to practise them: due soonest first, so the most
     * overdue lead, whether or not they are due yet. Callers take as many as
     * they want, e.g. nextUp()->limit(5).
     *
     * @param  Builder<Flashcard>  $query
     */
    #[Scope]
    protected function nextUp(Builder $query): void
    {
        $query->orderBy('due')->orderBy('id');
    }

    /**
     * Record how well David recalled this flashcard and reschedule it. Locks
     * the row first, so two grades arriving at once are applied in turn
     * rather than one overwriting the other.
     */
    public function grade(Rating $rating, ?DateTimeInterface $reviewedAt = null, ?int $reviewDurationMs = null): Review
    {
        $reviewedAt ??= now();

        [$review, $attributes] = DB::transaction(function () use ($rating, $reviewedAt, $reviewDurationMs): array {
            $locked = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $before = $locked->schedule;

            $locked->schedule = app(Scheduler::class)->review($before, $rating, $reviewedAt);
            $locked->save();

            $review = $locked->reviews()->create([
                'rating' => $rating,
                'reviewed_at' => CardSchedule::inUtc($reviewedAt),
                'review_duration_ms' => $reviewDurationMs,
                'schedule_before' => CardScheduleCast::columns($before),
                'schedule_after' => CardScheduleCast::columns($locked->schedule),
            ]);

            return [$review, $locked->getAttributes()];
        });

        // Only once committed, so a rolled-back grade leaves this instance as
        // it was. Two steps on purpose: setRawAttributes(sync: true) syncs
        // before it clears the cast cache, so a schedule read earlier would
        // be written back over the new one.
        $this->setRawAttributes($attributes);
        $this->syncOriginal();

        return $review;
    }
}
