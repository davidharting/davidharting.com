<?php

namespace App\Policies;

use App\Models\Memory;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * PROTOTYPE (#251): "who counts as a participant" is still fog on the map, so the owner stands in for it here.
 */
class MemoryPolicy
{
    public function view(?User $user, Memory $memory): Response
    {
        return $user?->is($memory->user)
            ? Response::allow()
            : Response::denyWithStatus(404);
    }
}
