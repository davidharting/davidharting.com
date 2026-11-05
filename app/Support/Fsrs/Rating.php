<?php

namespace App\Support\Fsrs;

/**
 * How well David recalled a card when it was reviewed. The integer values
 * match FSRS and py-fsrs, and the scheduler's formulas use them directly.
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
