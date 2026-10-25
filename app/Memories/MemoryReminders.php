<?php

namespace App\Memories;

use App\Models\MemoryEmail;
use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Decides which reminders about the latest open month are due, and claims
 * them. Run it as often as you like: each email is claimed only once.
 */
class MemoryReminders
{
    /**
     * Claim and queue every reminder due now that hasn't been claimed yet.
     *
     * A reminder is due from 9am Eastern on its day until the next one takes
     * over, so a run that was missed catches up later: the month open reminder
     * until the nudge is due on the 15th, and the nudge until the month after
     * opens. Neither goes to someone who has already shared.
     *
     * @return Collection<int, MemoryEmail> The emails claimed by this run.
     */
    public function claimDue(): Collection
    {
        if (! MemoryParticipant::featureIsEnabled()) {
            return new Collection;
        }

        $month = Month::of(Month::latestOpen());

        if (! $this->openedWithTwoParticipants($month)) {
            return new Collection;
        }

        $kind = $this->kindDue($month);

        if ($kind === null) {
            return new Collection;
        }

        return User::query()
            ->whereHas('memoryParticipant')
            ->orderBy('id')
            ->get()
            ->reject(fn (User $user): bool => (bool) $month->memoryOf($user)?->isShared())
            ->map(fn (User $user): ?MemoryEmail => MemoryEmail::claim($user, $month, $kind))
            ->filter()
            ->values();
    }

    /**
     * Emails are only for months that open once both participants exist, so
     * adding the second never sends reminders about older months.
     */
    private function openedWithTwoParticipants(Month $month): bool
    {
        $secondJoinedAt = CarbonImmutable::parse(MemoryParticipant::query()->max('created_at'));

        return $month->opensAt()->greaterThanOrEqualTo($secondJoinedAt);
    }

    private function kindDue(Month $month): ?MemoryEmailKind
    {
        $now = CarbonImmutable::now();

        if ($now->greaterThanOrEqualTo($month->nudgeDueAt())) {
            return MemoryEmailKind::Nudge;
        }

        if ($now->greaterThanOrEqualTo($month->reminderDueAt())) {
            return MemoryEmailKind::MonthOpened;
        }

        return null;
    }
}
