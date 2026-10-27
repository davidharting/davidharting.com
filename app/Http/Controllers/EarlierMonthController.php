<?php

namespace App\Http\Controllers;

use App\Models\Month;
use App\Support\CalendarMonth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The way into writing about a month before launch: pick one from January
 * 2016 to the month before launch, then go to its editor. Launch and later
 * months are already on the list. The routes only let participants in.
 */
class EarlierMonthController extends Controller
{
    public function show(): View
    {
        return view('memories.earlier', [
            'earliest' => Month::earliest(),
            'latest' => $this->latest(),
        ]);
    }

    /**
     * Send the viewer to the editor for the month they picked.
     */
    public function open(Request $request): RedirectResponse
    {
        $earliest = Month::earliest();
        $latest = $this->latest();

        $validated = $request->validate([
            'month' => [
                'required',
                'date_format:Y-m',
                "after_or_equal:{$earliest->toYearMonth()}",
                "before_or_equal:{$latest->toYearMonth()}",
            ],
        ], [
            'month.date_format' => 'Write the month as YYYY-MM, for example 2019-07.',
            'month.*' => "Pick a month from {$earliest->label()} to {$latest->label()}.",
        ]);

        return redirect()->route('memories.write', $validated['month']);
    }

    /**
     * The latest month the picker offers: the one before launch.
     */
    private function latest(): CalendarMonth
    {
        return Month::launch()->previous();
    }
}
