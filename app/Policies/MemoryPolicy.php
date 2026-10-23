<?php

namespace App\Policies;

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MemoryPolicy
{
    /**
     * Only the two participants may see monthly memories, and only while the
     * feature is enabled. Everyone else, guests included, gets a 404.
     */
    public function viewAny(?User $user): Response
    {
        if ($user?->memoryParticipant !== null && MemoryParticipant::featureIsEnabled()) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    /**
     * Who may see one month's page. For now that is whoever may see the
     * list; whether the month exists is the controller's call.
     */
    public function viewMonth(?User $user): Response
    {
        return $this->viewAny($user);
    }

    /**
     * Who may start a memory of their own. For now that is whoever may see
     * the list; whether the month is open is the caller's call.
     */
    public function create(?User $user): Response
    {
        return $this->viewAny($user);
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

        return $this->viewAny($user);
    }
}
