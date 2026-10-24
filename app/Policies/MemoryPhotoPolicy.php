<?php

namespace App\Policies;

use App\Models\MemoryPhoto;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Who may see a memory's photos. A photo has no rule of its own: it goes
 * wherever its memory goes.
 */
class MemoryPhotoPolicy
{
    /**
     * Asks the gate rather than MemoryPolicy::view directly, so the memory
     * policy's before() check applies too.
     */
    public function view(?User $user, MemoryPhoto $photo): Response
    {
        return Gate::forUser($user)->inspect('view', $photo->memory);
    }
}
