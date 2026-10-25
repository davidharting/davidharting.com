<?php

namespace App\Memories;

use App\Models\MemoryEmail;
use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Models\User;
use App\Support\CalendarMonth;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Decides which reveal and late-share emails are due, and claims them. Run
 * it as often as you like: each email is claimed only once.
 *
 * Between them the two emails leave each person's inbox holding every memory
 * of the month that was shared: the reveal brings the month's memories once
 * there is the other person's to read, and the late share brings their own
 * if they share it after that.
 */
class RevealEmails
{
    /**
     * Claim and queue every reveal and late-share email due for every month
     * that opened once both participants existed. A reveal email always
     * catches up, however long ago its month revealed.
     *
     * @return Collection<int, MemoryEmail> The emails claimed by this run.
     */
    public function claimAllDue(): Collection
    {
        if (! MemoryParticipant::featureIsEnabled()) {
            return new Collection;
        }

        $secondJoinedAt = CarbonImmutable::parse(MemoryParticipant::query()->max('created_at'));

        return Month::query()
            ->whereBetween('id', [
                CalendarMonth::containing($secondJoinedAt)->previous()->toDateString(),
                Month::latestOpen()->toDateString(),
            ])
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

    private function claimReveal(Month $month, User $recipient): ?MemoryEmail
    {
        $otherMemory = $month->memoryOf(MemoryParticipant::otherThan($recipient));

        if (! $otherMemory?->isShared()) {
            return null;
        }

        return MemoryEmail::claim($recipient, $month, MemoryEmailKind::Reveal);
    }

    private function claimLateShare(Month $month, User $recipient): ?MemoryEmail
    {
        $reveal = MemoryEmail::query()
            ->whereBelongsTo($recipient)
            ->whereBelongsTo($month)
            ->where('kind', MemoryEmailKind::Reveal)
            ->first();
        $memory = $month->memoryOf($recipient);

        if ($reveal === null || ! $memory?->isShared() || $memory->shared_at->lessThanOrEqualTo($reveal->created_at)) {
            return null;
        }

        return MemoryEmail::claim($recipient, $month, MemoryEmailKind::LateShare);
    }
}
