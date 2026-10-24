<?php

namespace App\Policies;

use App\Models\MemoryPhoto;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Access rules for a memory's photos.
 */
class MemoryPhotoPolicy
{
    /**
     * A photo has no rule of its own: whoever may view its memory may view
     * it. This asks the gate rather than calling MemoryPolicy::view, so the
     * memory policy's before() check applies too.
     */
    public function view(?User $user, MemoryPhoto $photo): Response
    {
        return Gate::forUser($user)->inspect('view', $photo->memory);
    }
}
