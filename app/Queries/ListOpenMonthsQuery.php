<?php

namespace App\Queries;

use App\Models\Memory;
use App\Support\CalendarMonth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The months memories can be written about: from the launch month to the
 * latest open month. The launch month is the earliest month anyone has a
 * memory for, or the latest open month before anyone has written one.
 *
 * Open here has two bounds. A month must have opened, which is the clock
 * rule on CalendarMonth, and it must not be before the launch month, which takes
 * the database.
 *
 * This query performs no authorization. The caller is expected to have
 * checked that the viewer may see monthly memories.
 */
class ListOpenMonthsQuery
{
    /**
     * Every open month, newest first, including months nobody wrote about.
     *
     * @return Collection<int, CalendarMonth>
     */
    public function execute(): Collection
    {
        $latest = CalendarMonth::latestOpen()->toDateString();

        $rows = DB::select(<<<'SQL'
            select to_char(month, 'YYYY-MM-DD') as month
            from generate_series(
                coalesce((select min(month) from memories), ?::date),
                ?::date,
                interval '1 month'
            ) as month
            order by month desc
            SQL, [$latest, $latest]);

        return collect($rows)->map(fn (object $row) => CalendarMonth::parse($row->month));
    }

    /**
     * Whether the month is one of the open months, without listing them all.
     */
    public function contains(CalendarMonth $month): bool
    {
        if (! $month->hasOpened()) {
            return false;
        }

        return $month->equals(CalendarMonth::latestOpen())
            || Memory::query()->where('month', '<=', $month->toDateString())->exists();
    }
}
