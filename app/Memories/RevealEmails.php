<?php

namespace App\Memories;

use App\Models\MemoryEmail;
use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Models\User;
use App\Support\CalendarMonth;
use Illuminate\Support\Collection;

/**
 * Decides which reveal emails are due, and claims them. Run it as often as
 * you like: each email is claimed only once.
 */
class RevealEmails
{
    /**
     * Claim and queue every reveal email due for every month that opened
     * once both participants existed. A reveal email always catches up,
     * however long ago its month revealed. The range starts a month early
     * because the second participant may have joined on the last day of a
     * month in UTC; claimDue() drops it if it opened before.
     *
     * @return Collection<int, MemoryEmail> The emails claimed by this run.
     */
    public function claimAllDue(): Collection
    {
        if (! MemoryParticipant::featureIsEnabled()) {
            return new Collection;
        }

        $secondJoinedAt = MemoryParticipant::secondJoinedAt();

        return Month::query()
            ->whereBetween('id', [
                CalendarMonth::containing($secondJoinedAt)->previous()->toDateString(),
                Month::latestOpen()->toDateString(),
            ])
            ->with('memories')
            ->orderBy('id')
            ->get()
            ->flatMap(fn (Month $month): Collection => $this->claimDue($month))
            ->values();
    }

    /**
     * Claim and queue the month's reveal emails that are due now and haven't
     * been claimed yet. A person's reveal email is due once the month has
     * revealed and the other person's memory is shared: when both share,
     * both get one; at the reveal deadline, only someone with something to
     * read does; and a memory shared after the reveal sends one to the other
     * person if they haven't had theirs.
     *
     * @return Collection<int, MemoryEmail> The emails claimed by this run.
     */
    public function claimDue(Month $month): Collection
    {
        if (! MemoryParticipant::featureIsEnabled() || ! $month->openedWithBothParticipants() || ! $month->hasRevealed()) {
            return new Collection;
        }

        return User::query()
            ->whereHas('memoryParticipant')
            ->orderBy('id')
            ->get()
            ->filter(fn (User $user): bool => (bool) $month->memoryOf(MemoryParticipant::otherThan($user))?->isShared())
            ->map(fn (User $user): ?MemoryEmail => MemoryEmail::claim($user, $month, MemoryEmailKind::Reveal))
            ->filter()
            ->values();
    }
}
