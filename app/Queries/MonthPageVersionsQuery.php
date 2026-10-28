<?php

namespace App\Queries;

use App\Models\Memory;
use App\Models\MemoryPhoto;
use App\Models\Month;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A short fingerprint of each month's page as one viewer sees it. It
 * changes whenever something the viewer can see there changes, and never
 * for something they can't, such as the other person's unshared draft.
 */
class MonthPageVersionsQuery
{
    /**
     * One query for all the months, however many there are.
     *
     * @param  Collection<int, Month>  $months  with their memories and photos loaded, as ListOpenMonthsQuery returns them
     * @return array<string, string> fingerprints keyed by the month's first day
     */
    public function execute(Collection $months, User $viewer): array
    {
        $reactions = $this->reactionsByMonth($months);

        return $months->mapWithKeys(function (Month $month) use ($viewer, $reactions) {
            $firstDay = $month->calendarMonth->toDateString();

            return [$firstDay => substr(md5(json_encode([
                $month->hasRevealed(),
                $month->memories
                    ->sortBy('id')
                    ->map(fn (Memory $memory) => $this->visibleStateOf($memory, $month, $viewer))
                    ->values(),
                $reactions->get($firstDay),
            ])), 0, 10)];
        })->all();
    }

    /**
     * All of the viewer's own memory and of a revealed one; of the other
     * person's unrevealed memory, only what the page says about it: when it
     * was started and when it was shared.
     *
     * @return array<int, mixed>
     */
    private function visibleStateOf(Memory $memory, Month $month, User $viewer): array
    {
        if ($memory->user_id !== $viewer->id && ! $month->isRevealed($memory)) {
            return [$memory->id, $memory->created_at, $memory->shared_at];
        }

        return [
            $memory->id,
            $memory->lock_version,
            $memory->updated_at,
            $memory->shared_at,
            $memory->photos->map(fn (MemoryPhoto $photo) => [$photo->id, $photo->updated_at])->values(),
        ];
    }

    /**
     * How many reactions each month's memories have had, and when they last
     * changed. Reactions are only ever on revealed memories.
     *
     * @param  Collection<int, Month>  $months
     * @return Collection<string, object> keyed by the month's first day
     */
    private function reactionsByMonth(Collection $months): Collection
    {
        return DB::table('memory_reactions')
            ->join('memories', 'memories.id', '=', 'memory_reactions.memory_id')
            ->whereIn('memories.month_id', $months->map(fn (Month $month) => $month->calendarMonth->toDateString()))
            ->groupBy('memories.month_id')
            ->selectRaw(<<<'SQL'
                memories.month_id::text as month,
                count(*) as reactions,
                greatest(max(memory_reactions.updated_at), max(memory_reactions.deleted_at)) as changed_at
                SQL)
            ->get()
            ->keyBy('month');
    }
}
