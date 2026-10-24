<?php

namespace App\Policies;

use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Models\User;
use App\Queries\ListOpenMonthsQuery;
use Illuminate\Auth\Access\Response;

/**
 * Who may look at months of memories and write in them. Every denial is a
 * 404, so nobody else can tell the feature exists.
 */
class MonthPolicy
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
     * A participant may look at any open month, from the launch month to the
     * latest open one.
     */
    public function view(?User $user, Month $month): Response
    {
        $viewAny = $this->viewAny($user);

        if ($viewAny->denied()) {
            return $viewAny;
        }

        if (! (new ListOpenMonthsQuery)->contains($month->calendarMonth)) {
            return Response::denyAsNotFound();
        }

        return Response::allow();
    }

    /**
     * Whether the user may draft and share their own memory for the month.
     * For now that is any month they may look at.
     */
    public function write(?User $user, Month $month): Response
    {
        return $this->view($user, $month);
    }
}
