<?php

namespace App\Memories;

use App\Models\Month;
use App\Notifications\Memories\MonthOpened;
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

    public function notification(Month $month): Notification
    {
        return match ($this) {
            self::MonthOpened => new MonthOpened($month),
        };
    }
}
