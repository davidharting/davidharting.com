<?php

namespace App\Memories;

use App\Models\MemoryEmail;
use App\Notifications\Memories\LateShare;
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
     * Once the month has revealed and the other person's memory can be read:
     * every memory shared by the time it was claimed.
     */
    case Reveal = 'reveal';

    /**
     * To someone who shares their memory after their reveal email was
     * claimed: a copy of their own memory, so their inbox holds the month.
     */
    case LateShare = 'late_share';

    public function notification(MemoryEmail $email): Notification
    {
        return match ($this) {
            self::MonthOpened => new MonthOpened($email->month),
            self::Nudge => new Nudge($email->month),
            self::Reveal => new Revealed($email->month, $email->created_at),
            self::LateShare => new LateShare($email->month),
        };
    }
}
