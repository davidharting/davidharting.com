<?php

namespace App\Queries;

use App\Models\Month;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A short fingerprint of everything on each month's page: its memories,
 * their reactions and photos, and whether it has revealed. Anything that
 * changes what the page shows changes the fingerprint.
 *
 * The list links each tile to its month with the fingerprint attached, so
 * a month the browser prefetched is never shown after it has changed.
 *
 * This query performs no authorization; the fingerprint says nothing about
 * what it covers.
 */
class MonthPageVersionsQuery
{
    /**
     * One query for all the months, however many there are.
     *
     * @param  Collection<int, Month>  $months
     * @return array<string, string> fingerprints keyed by month id
     */
    public function execute(Collection $months): array
    {
        if ($months->isEmpty()) {
            return [];
        }

        $contents = DB::table('memories')
            ->leftJoin('memory_reactions', 'memory_reactions.memory_id', '=', 'memories.id')
            ->leftJoin('memory_photos', 'memory_photos.memory_id', '=', 'memories.id')
            ->whereIn('memories.month_id', $months->map(fn (Month $month) => $month->calendarMonth->toDateString()))
            ->groupBy('memories.month_id')
            ->selectRaw(<<<'SQL'
                memories.month_id,
                count(distinct memories.id) as memories,
                max(memories.updated_at) as memories_changed_at,
                count(distinct memory_reactions.id) as reactions,
                greatest(max(memory_reactions.updated_at), max(memory_reactions.deleted_at)) as reactions_changed_at,
                count(distinct memory_photos.id) as photos,
                greatest(max(memory_photos.updated_at), max(memory_photos.deleted_at)) as photos_changed_at
                SQL)
            ->get()
            ->keyBy(fn (object $row) => substr((string) $row->month_id, 0, 10));

        return $months->mapWithKeys(fn (Month $month) => [
            $month->calendarMonth->toDateString() => substr(md5(json_encode([
                $month->hasRevealed(),
                $contents->get($month->calendarMonth->toDateString()),
            ])), 0, 10),
        ])->all();
    }
}
