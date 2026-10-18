<?php

namespace App\Queries;

use App\Models\Memory;
use App\Support\Month;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The months memories can be written about: from the launch month to the
 * latest open month. The launch month is the earliest month anyone has a
 * memory for, or the latest open month before anyone has written one.
 *
 * This query performs no authorization. The caller is expected to have
 * checked that the viewer may see monthly memories.
 */
class ListOpenMonthsQuery
{
    /**
     * Every open month, newest first, including months nobody wrote about.
     *
     * @return Collection<int, Month>
     */
    public function execute(): Collection
    {
        $latest = Month::latestOpen()->toDateString();

        $rows = DB::select(<<<'SQL'
            select to_char(month, 'YYYY-MM-DD') as month
            from generate_series(
                coalesce((select min(month) from memories), ?::date),
                ?::date,
                interval '1 month'
            ) as month
            order by month desc
            SQL, [$latest, $latest]);

        return collect($rows)->map(fn (object $row) => Month::parse($row->month));
    }

    /**
     * Whether the month is one of the open months, without listing them all.
     */
    public function contains(Month $month): bool
    {
        if (! $month->isOpen()) {
            return false;
        }

        return $month->equals(Month::latestOpen())
            || Memory::query()->where('month', '<=', $month->toDateString())->exists();
    }
}
