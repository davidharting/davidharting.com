<?php

namespace App\Queries;

use App\Models\Month;
use App\Support\CalendarMonth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The months on the memories list: every month from the launch month to the
 * latest open month, and any earlier month either participant has written
 * about, a draft included.
 *
 * Every month up to the latest open one can be written about. The list
 * leaves out the months before launch nobody has written about, so it
 * doesn't stretch back to 2016.
 *
 * This query performs no authorization. The caller is expected to have
 * checked that the viewer may see monthly memories.
 */
class ListOpenMonthsQuery
{
    /**
     * Every listed month with its memories and their photos, newest first,
     * including months since launch that nobody wrote about.
     *
     * @return Collection<int, Month>
     */
    public function execute(): Collection
    {
        return $this->listed()
            ->with('memories.photos')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The nearest listed month before the provided one, or null if there is none.
     */
    public function previous(CalendarMonth $calendarMonth): ?CalendarMonth
    {
        $id = $this->listed()->where('id', '<', $calendarMonth->toDateString())->max('id');

        return $id === null ? null : CalendarMonth::parse($id);
    }

    /**
     * The nearest listed month after the provided one, or null if there is none.
     */
    public function next(CalendarMonth $calendarMonth): ?CalendarMonth
    {
        $id = $this->listed()->where('id', '>', $calendarMonth->toDateString())->min('id');

        return $id === null ? null : CalendarMonth::parse($id);
    }

    /**
     * @return Builder<Month>
     */
    private function listed(): Builder
    {
        return Month::query()
            ->where('id', '<=', Month::latestOpen()->toDateString())
            ->where(fn (Builder $query) => $query
                ->where('id', '>=', Month::launch()->toDateString())
                ->orWhereHas('memories'));
    }
}
