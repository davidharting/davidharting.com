<?php

namespace App\Fsrs;

/**
 * How well a card was recalled when it was reviewed. Ports py-fsrs's
 * `Rating`: the integer values match, and the scheduler's formulas use them
 * directly. An app may offer fewer choices, such as only Again and Good.
 */
enum Rating: int
{
    /** Forgot it. */
    case Again = 1;

    /** Recalled it, with serious effort. */
    case Hard = 2;

    /** Recalled it after a moment's thought. */
    case Good = 3;

    /** Recalled it instantly. */
    case Easy = 4;
}
