<?php

namespace App\Policies;

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
}
