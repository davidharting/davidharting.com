<?php

namespace App\Http\Controllers;

use App\Models\Month;
use Illuminate\View\View;

/**
 * The way into writing about a month before launch: pick one from January
 * 2016 to the month before launch, then go to its editor. Launch and later
 * months are already on the list. The route only lets participants in.
 */
class EarlierMonthController extends Controller
{
    /**
     * Every month the picker offers, newest first, so no browser can offer
     * a month outside the range. Each option is the month's editor URL.
     */
    public function __invoke(): View
    {
        $earliest = Month::earliest();
        $months = [];

        for ($month = Month::launch()->previous(); ! $earliest->isAfter($month); $month = $month->previous()) {
            $months[] = $month;
        }

        return view('memories.earlier', ['months' => $months]);
    }
}
