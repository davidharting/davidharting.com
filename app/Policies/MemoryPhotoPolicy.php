<?php

namespace App\Policies;

use App\Models\MemoryPhoto;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who may see a memory's photos. A photo has no rule of its own: it goes
 * wherever its memory goes.
 */
class MemoryPhotoPolicy
{
    public function __construct(private MemoryPolicy $memories) {}

    public function view(?User $user, MemoryPhoto $photo): Response
    {
        return $this->memories->view($user, $photo->memory);
    }
}
