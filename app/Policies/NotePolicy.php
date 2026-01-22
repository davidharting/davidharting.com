<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class NotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(?User $user, Note $note): Response
    {
        if ($user?->is_admin || $note->visible) {
            return Response::allow();
        }

        return Response::denyWithStatus(404);
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Note $note): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, Note $note): bool
    {
        return $user->is_admin;
    }

    public function restore(User $user, Note $note): bool
    {
        return $user->is_admin;
    }

    public function forceDelete(User $user, Note $note): bool
    {
        return $user->is_admin;
    }
}
