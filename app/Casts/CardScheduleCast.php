<?php

namespace App\Casts;

use App\Fsrs\CardSchedule;
use App\Fsrs\CardState;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Maps an FSRS CardSchedule onto the six columns that store it: state, step,
 * stability, difficulty, due and last_review.
 *
 * @implements CastsAttributes<CardSchedule, CardSchedule>
 */
class CardScheduleCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): CardSchedule
    {
        return new CardSchedule(
            state: CardState::from((int) $attributes['state']),
            step: $attributes['step'] === null ? null : (int) $attributes['step'],
            stability: $attributes['stability'] === null ? null : (float) $attributes['stability'],
            difficulty: $attributes['difficulty'] === null ? null : (float) $attributes['difficulty'],
            due: self::toDate($attributes['due']),
            lastReview: $attributes['last_review'] === null ? null : self::toDate($attributes['last_review']),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (! $value instanceof CardSchedule) {
            throw new InvalidArgumentException('A flashcard schedule must be an '.CardSchedule::class.'.');
        }

        return [
            ...self::columns($value),
            'stability' => self::exactly($value->stability),
            'difficulty' => self::exactly($value->difficulty),
        ];
    }

    /**
     * A float as the shortest string that reads back as exactly the same
     * float (serialize_precision -1). PDO binds
     * a float using the `precision` ini setting, 14 digits by default, which
     * would round stability and difficulty on every save and drift replayed
     * schedules away from py-fsrs.
     */
    private static function exactly(?float $value): ?string
    {
        return $value === null ? null : var_export($value, true);
    }

    /**
     * The column values for a schedule, also used to snapshot it on a Review.
     *
     * @return array{state: int, step: ?int, stability: ?float, difficulty: ?float, due: string, last_review: ?string}
     */
    public static function columns(CardSchedule $schedule): array
    {
        return [
            'state' => $schedule->state->value,
            'step' => $schedule->step,
            'stability' => $schedule->stability,
            'difficulty' => $schedule->difficulty,
            'due' => $schedule->due->format('Y-m-d H:i:s.uP'),
            'last_review' => $schedule->lastReview?->format('Y-m-d H:i:s.uP'),
        ];
    }

    private static function toDate(DateTimeInterface|string $value): DateTimeInterface
    {
        return $value instanceof DateTimeInterface ? $value : Carbon::parse($value);
    }
}
