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
     * Nobody may read or write a memory unless they may see monthly memories
     * at all. Guests and non-participants get the MonthPolicy's 404.
     */
    public function before(?User $user): ?Response
    {
        $viewAny = $this->months->viewAny($user);

        return $viewAny->denied() ? $viewAny : null;
    }

    /**
     * The author may always read their memory. The other participant may
     * once the month has revealed it.
     */
    public function view(User $user, Memory $memory): Response
    {
        if ($user->id === $memory->user_id || $memory->month->isRevealed($memory)) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    /**
     * Who may start a memory of their own. For now that is any participant;
     * whether they may write in the month is the MonthPolicy's call.
     */
    public function create(User $user): Response
    {
        return Response::allow();
    }

    /**
     * Only its author may edit a memory.
     */
    public function update(User $user, Memory $memory): Response
    {
        if ($user->id !== $memory->user_id) {
            return Response::denyAsNotFound();
        }

        return Response::allow();
    }

    /**
     * Only the other participant may react to a memory, and only once the
     * month has revealed it to them.
     */
    public function react(User $user, Memory $memory): Response
    {
        if ($user->id === $memory->user_id) {
            return Response::denyAsNotFound();
        }

        return $this->view($user, $memory);
    }
}
