<?php

namespace App\Queries;

use App\Models\Memory;
use App\Models\Month;
use App\Support\CalendarMonth;
use Illuminate\Database\Eloquent\Collection;

/**
 * The months memories can be written about: from the launch month to the
 * latest open month. The launch month is the earliest month anyone has a
 * memory for, or the latest open month before anyone has written one.
 *
 * Open here has two bounds. A month must have opened, which is the clock
 * rule on Month, and it must not be before the launch month, which takes
 * the database.
 *
 * This query performs no authorization. The caller is expected to have
 * checked that the viewer may see monthly memories.
 */
class ListOpenMonthsQuery
{
    /**
     * Every open month with its memories and their photos, newest first, including months
     * nobody wrote about.
     *
     * @return Collection<int, Month>
     */
    public function execute(): Collection
    {
        return Month::query()
            ->whereBetween('id', [$this->launchMonth()->toDateString(), Month::latestOpen()->toDateString()])
            ->with('memories.photos')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Whether the month is one of the open months, without listing them all.
     */
    public function contains(CalendarMonth $calendarMonth): bool
    {
        return ! $calendarMonth->isAfter(Month::latestOpen())
            && ! $this->launchMonth()->isAfter($calendarMonth);
    }

    private function launchMonth(): CalendarMonth
    {
        $earliest = Memory::query()->min('month_id');

        return $earliest === null ? Month::latestOpen() : CalendarMonth::parse($earliest);
    }
}
