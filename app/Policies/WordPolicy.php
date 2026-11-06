<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Word;

/**
 * Words are David's private study list, so every ability is the
 * administrate gate and nothing else.
 */
class WordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('administrate');
    }

    public function view(User $user, Word $word): bool
    {
        return $user->can('administrate');
    }

    public function create(User $user): bool
    {
        return $user->can('administrate');
    }

    public function update(User $user, Word $word): bool
    {
        return $user->can('administrate');
    }

    public function delete(User $user, Word $word): bool
    {
        return $user->can('administrate');
    }
}
