<?php

namespace App\Memories;

use App\Support\CalendarMonth;

/**
 * PROTOTYPE (mobile polish, #334): which list-to-month transition a month
 * demonstrates, so every option can be compared on one preview.
 */
final class PrototypeTransition
{
    public static function for(CalendarMonth $month): string
    {
        if (app()->runningUnitTests()) {
            return 'title';
        }

        return match ($month->name()) {
            'September' => 'expand',
            'August' => 'photo',
            'July' => 'push',
            'June' => 'fade',
            default => 'title',
        };
    }

    public static function fliesTitle(CalendarMonth $month): bool
    {
        return in_array(self::for($month), ['title', 'expand', 'photo'], true);
    }
}
