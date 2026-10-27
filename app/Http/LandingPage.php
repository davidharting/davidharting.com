<?php

namespace App\Http;

use App\Models\Month;
use App\Models\User;

/**
 * Picks the page a signed-in user lands on when nothing else asked for one.
 */
final class LandingPage
{
    /**
     * A memories participant who isn't the admin goes straight to their
     * memories, everyone else to the Account page.
     */
    public static function for(User $user): string
    {
        if ($user->can('viewAny', Month::class) && $user->cannot('administrate')) {
            return route('memories.index', absolute: false);
        }

        return route('profile.edit', absolute: false);
    }
}
