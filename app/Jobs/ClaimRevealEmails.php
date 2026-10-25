<?php

namespace App\Jobs;

use App\Memories\MemoryEmailKind;
use App\Models\MemoryEmail;
use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued after a memory is shared: claims the reveal emails that are due for
 * its month. Safe to run any number of times, since each email is claimed
 * only once.
 */
class ClaimRevealEmails implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public Month $month) {}

    /**
     * A person's reveal email is due once the month has revealed and the
     * other person's memory is shared. So when the second person shares,
     * both get one, and a memory shared after the reveal deadline tells the
     * other person it's there. Nothing is sent at the deadline itself.
     */
    public function handle(): void
    {
        if (! MemoryParticipant::featureIsEnabled() || ! $this->month->openedWithBothParticipants() || ! $this->month->hasRevealed()) {
            return;
        }

        User::query()
            ->whereHas('memoryParticipant')
            ->orderBy('id')
            ->get()
            ->filter(fn (User $user): bool => (bool) $this->month->memoryOf(MemoryParticipant::otherThan($user))?->isShared())
            ->each(fn (User $user): ?MemoryEmail => MemoryEmail::claim($user, $this->month, MemoryEmailKind::Reveal));
    }
}
