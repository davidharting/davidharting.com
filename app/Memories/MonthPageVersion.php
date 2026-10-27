<?php

namespace App\Memories;

use App\Models\Month;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PROTOTYPE (mobile polish, #334): a short fingerprint of everything a month
 * page shows one viewer. Tile links carry it, so a month prefetched by
 * wire:navigate is never reused after anything on it changes.
 */
final class MonthPageVersion
{
    /**
     * @param  Collection<int, Month>  $months
     * @return array<int, string> keyed by month id
     */
    public static function forMonths(Collection $months, User $viewer): array
    {
        $rows = collect(DB::select(<<<'SQL'
            select
                m.month_id,
                count(distinct m.id) as memories,
                max(m.updated_at) as memories_at,
                count(distinct r.id) as reactions,
                greatest(max(r.updated_at), max(r.deleted_at)) as reactions_at,
                count(distinct p.id) as photos,
                greatest(max(p.updated_at), max(p.deleted_at)) as photos_at
            from memories m
            left join memory_reactions r on r.memory_id = m.id
            left join memory_photos p on p.memory_id = m.id
            group by m.month_id
            SQL))->keyBy('month_id');

        return $months->mapWithKeys(fn (Month $month) => [
            $month->id => substr(md5(implode('|', [
                $viewer->id,
                $month->hasRevealed() ? 'revealed' : 'open',
                json_encode($rows->get($month->id)),
            ])), 0, 10),
        ])->all();
    }

    public static function for(Month $month, User $viewer): string
    {
        return self::forMonths(collect([$month]), $viewer)[$month->id];
    }
}
