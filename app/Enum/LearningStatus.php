<?php

namespace App\Enum;

/**
 * How well David knows a word, read off its flashcard's FSRS schedule. Every
 * flashcard has exactly one, in its `learning_status` column, which Postgres
 * generates from the schedule. The rule and its thresholds live in that
 * column's migration (add_learning_status_to_flashcards_table), and nowhere
 * else.
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
