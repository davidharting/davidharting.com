<?php

namespace App\Memories;

use App\Models\Month;
use App\Notifications\Memories\MonthOpened;
use App\Notifications\Memories\Nudge;
use App\Notifications\Memories\Revealed;
use Illuminate\Notifications\Notification;

/**
 * The emails monthly memories sends. Each person gets each kind at most once
 * per month.
 */
enum MemoryEmailKind: string
{
    /**
     * On the 1st: the month is open, write yours.
     */
    case MonthOpened = 'month_opened';

    /**
     * On the 15th, to whoever hasn't shared: where the other person stands.
     */
    case Nudge = 'nudge';

    /**
     * Once the other person's memory can be read: a heads-up and a link.
     */
    case Reveal = 'reveal';

    public function notification(Month $month): Notification
    {
        return match ($this) {
            self::MonthOpened => new MonthOpened($month),
            self::Nudge => new Nudge($month),
            self::Reveal => new Revealed($month),
        };
    }
}
