<?php

namespace App\Fsrs;

/**
 * Where a card is in its FSRS life cycle. Ports py-fsrs's `State`: the
 * integer values match,
 * so a stored state can be replayed or compared against it. There is no
 * separate "new" state: a card that has never been reviewed is Learning at
 * step 0 with no stability or difficulty.
 */
enum CardState: int
{
    /** Being learned for the first time, moving through the short learning steps. */
    case Learning = 1;

    /** Learned, and scheduled days or months ahead. */
    case Review = 2;

    /** Forgotten after being learned, moving through the short relearning steps. */
    case Relearning = 3;
}
