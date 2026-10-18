<?php

namespace App\Queries;

use App\Models\Memory;
use App\Models\User;
use App\Support\MemoryMonth;
use App\Support\Month;
use Illuminate\Support\Collection;

class ListMemoryMonthsQuery
{
    /**
     * This query performs no authorization. The caller has already established
     * that both users are the feature's two participants.
     */
    public function __construct(
        public User $viewer,
        public User $other,
    ) {}

    /**
     * Every month from the launch month to the latest open one, newest first,
     * including months nobody wrote about. The launch month is the earliest
     * month anyone has a memory for, or the latest open month before anyone has.
     *
     * @return Collection<int, MemoryMonth>
     */
    public function execute(): Collection
    {
        $memories = Memory::query()
            ->whereIn('user_id', [$this->viewer->id, $this->other->id])
            ->get()
            ->groupBy(fn (Memory $memory) => $memory->month->toDateString());

        $latest = Month::latestOpen();
        $earliest = $memories->keys()->sort()->first();
        $launch = $earliest === null ? $latest : Month::parse($earliest);

        $months = collect();

        for ($month = $latest; $month->toDateString() >= $launch->toDateString(); $month = $month->previous()) {
            $forMonth = $memories->get($month->toDateString(), collect());

            $months->push(new MemoryMonth(
                month: $month,
                viewer: $this->viewer,
                other: $this->other,
                mine: $forMonth->firstWhere('user_id', $this->viewer->id),
                theirs: $forMonth->firstWhere('user_id', $this->other->id),
            ));
        }

        return $months;
    }
}
