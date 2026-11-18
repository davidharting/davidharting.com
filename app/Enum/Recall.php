<?php

namespace App\Enum;

use App\Fsrs\Rating;

/**
 * How David did on a flashcard, in the two choices practice offers him.
 * FSRS has four ratings; practice uses only Again and Good.
 */
enum Recall: string
{
    case GotIt = 'got_it';

    case MissedIt = 'missed_it';

    /**
     * The FSRS rating this result is recorded as.
     */
    public function rating(): Rating
    {
        return match ($this) {
            self::GotIt => Rating::Good,
            self::MissedIt => Rating::Again,
        };
    }
}
