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
 * Queued after a memory is shared: sends whatever emails the share made due
 * for its month. Safe to run any number of times, since each email goes out
 * at most once.
 */
class SendShareNotificationsIfNeeded implements ShouldQueue
{
    use Queueable;

    /**
     * The first try and two more, a minute and then five minutes apart.
     */
    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public Month $month) {}

    /**
     * Each participant gets the month's reveal email once the month has
     * revealed and the other person's memory is shared. So when the second
     * person shares, before or after the reveal deadline, both get one; when
     * the only shared memory is shared after the deadline, just the other
     * person does. Nothing is sent at the deadline itself, since no share
     * queues this job then.
     */
    public function handle(): void
    {
        if (! MemoryParticipant::featureIsEnabled() || ! $this->month->hasRevealed()) {
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
