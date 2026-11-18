<?php

namespace App\Enum;

use App\Fsrs\CardSchedule;

/**
 * How well David knows a word, read off its flashcard's FSRS schedule. Every
 * word has exactly one status: the first of these, in order, that fits.
 *
 * The rule is written twice, once in PHP for a single schedule and once in
 * SQL for filtering and counting a list. Both read the thresholds below, and
 * a test holds them to the same answers.
 */
enum LearningStatus: string
{
    /** Never practised. */
    case New = 'new';

    /** FSRS expects David to still remember it three weeks or more from now. */
    case Mastered = 'mastered';

    /** Not mastered, and hard for him: FSRS difficulty is high. */
    case Troublesome = 'troublesome';

    /** Everything else: practised, and on its way. */
    case Learning = 'learning';

    /**
     * Days of FSRS stability (until recall falls to 90%) at which a word
     * counts as mastered. A starting guess, to tune once real reviews exist.
     */
    public const float MASTERED_STABILITY_DAYS = 21.0;

    /**
     * FSRS difficulty, from 1 to 10, at which a word that is not mastered
     * counts as troublesome. It rises on each miss and drifts back down on
     * each "Got it". A starting guess, to tune once real reviews exist.
     */
    public const float TROUBLESOME_DIFFICULTY = 7.0;

    /**
     * The status of a flashcard with this schedule.
     */
    public static function of(CardSchedule $schedule): self
    {
        return match (true) {
            $schedule->lastReview === null => self::New,
            $schedule->stability >= self::MASTERED_STABILITY_DAYS => self::Mastered,
            $schedule->difficulty >= self::TROUBLESOME_DIFFICULTY => self::Troublesome,
            default => self::Learning,
        };
    }

    /**
     * The same rule as of(), as a SQL expression over a row of the
     * flashcards table, evaluating to a status's value.
     */
    public static function sql(): string
    {
        return sprintf(
            "case when %1\$s.last_review is null then '%2\$s'"
            ." when %1\$s.stability >= %3\$s then '%4\$s'"
            ." when %1\$s.difficulty >= %5\$s then '%6\$s'"
            ." else '%7\$s' end",
            'flashcards',
            self::New->value,
            var_export(self::MASTERED_STABILITY_DAYS, true),
            self::Mastered->value,
            var_export(self::TROUBLESOME_DIFFICULTY, true),
            self::Troublesome->value,
            self::Learning->value,
        );
    }

    /**
     * How the status reads to David.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Mastered => 'Mastered',
            self::Troublesome => 'Troublesome',
            self::Learning => 'Learning',
        };
    }
}
