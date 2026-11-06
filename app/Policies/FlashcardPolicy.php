<?php

namespace App\Policies;

use App\Models\Flashcard;
use App\Models\User;

/**
 * Flashcards, and the reviews logged on them, are David's private practice,
 * so every ability is the administrate gate and nothing else.
 */
class FlashcardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('administrate');
    }

    public function view(User $user, Flashcard $flashcard): bool
    {
        return $user->can('administrate');
    }

    /**
     * Grade a flashcard, logging a review and rescheduling it.
     */
    public function practice(User $user, Flashcard $flashcard): bool
    {
        return $user->can('administrate');
    }
}
