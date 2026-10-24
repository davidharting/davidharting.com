<?php

namespace App\Policies;

use App\Models\Memory;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who may read and write one memory. Whether the month itself may be seen
 * or written in is the MonthPolicy's call.
 */
class MemoryPolicy
{
    public function __construct(private MonthPolicy $months) {}

    /**
     * The author may always read their memory. The other participant may
     * once the month has revealed it.
     */
    public function view(?User $user, Memory $memory): Response
    {
        $viewAny = $this->months->viewAny($user);

        if ($viewAny->denied() || $user->id === $memory->user_id) {
            return $viewAny;
        }

        if ($memory->month->isRevealed($memory)) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    /**
     * Who may start a memory of their own. For now that is any participant;
     * whether they may write in the month is the MonthPolicy's call.
     */
    public function create(?User $user): Response
    {
        return $this->months->viewAny($user);
    }

    /**
     * Only its author may edit a memory, and only while they may see
     * memories at all.
     */
    public function update(?User $user, Memory $memory): Response
    {
        if ($user?->id !== $memory->user_id) {
            return Response::denyAsNotFound();
        }

        return $this->months->viewAny($user);
    }
}
