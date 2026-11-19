<?php

namespace App\Enum;

/**
 * How well David knows a word, read off its flashcard's FSRS schedule.
 * `flashcards.learning_status` is a generated column, and its definition is
 * the source of truth for which status a schedule gets. This enum only names
 * the values it can hold.
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
