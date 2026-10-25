<?php

namespace App\Memories;

use App\Models\MemoryEmail;
use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Models\User;
use App\Support\CalendarMonth;
use Illuminate\Support\Collection;

/**
 * Decides which reveal and late-share emails are due, and claims them. Run
 * it as often as you like: each email is claimed only once.
 *
 * The reveal brings a person the month's memories once there is the other
 * person's to read, and the late share brings their own if they share it
 * after that. So once both have shared, each inbox holds both memories. A
 * person who shares when the other never does gets no copy of their own.
 */
class RevealEmails
{
    /**
     * Claim and queue every reveal and late-share email due for every month
     * that opened once both participants existed. A reveal email always
     * catches up, however long ago its month revealed. The range starts a
     * month early because the second participant may have joined on the
     * last day of a month in UTC; claimDue() drops it if it opened before.
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
     * Claim and queue the month's reveal and late-share emails that are due
     * now and haven't been claimed yet.
     *
     * - The reveal email is due to a person once the month has revealed and
     *   the other person's memory is shared, so at the reveal deadline it
     *   goes only to someone who has something to read.
     * - The late-share email is due to a person who shared their memory
     *   after their reveal email was claimed, since that email went without it.
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
            ->flatMap(fn (User $user): array => [
                $this->claimReveal($month, $user),
                $this->claimLateShare($month, $user),
            ])
            ->filter()
            ->values();
    }

    /**
     * Due once the other person's memory is shared, in a revealed month.
     */
    private function claimReveal(Month $month, User $recipient): ?MemoryEmail
    {
        $otherMemory = $month->memoryOf(MemoryParticipant::otherThan($recipient));

        if (! $otherMemory?->isShared()) {
            return null;
        }

        return MemoryEmail::claim($recipient, $month, MemoryEmailKind::Reveal);
    }

    /**
     * Due when the recipient's own memory wasn't shared by the time their
     * reveal email was claimed, so it went without it.
     */
    private function claimLateShare(Month $month, User $recipient): ?MemoryEmail
    {
        $reveal = MemoryEmail::query()
            ->whereBelongsTo($recipient)
            ->whereBelongsTo($month)
            ->where('kind', MemoryEmailKind::Reveal)
            ->first();
        $memory = $month->memoryOf($recipient);

        if ($reveal === null || ! $memory?->isShared() || $memory->wasSharedBy($reveal->created_at)) {
            return null;
        }

        return MemoryEmail::claim($recipient, $month, MemoryEmailKind::LateShare);
    }
}
