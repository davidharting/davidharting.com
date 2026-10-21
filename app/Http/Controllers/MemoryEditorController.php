<?php

namespace App\Http\Controllers;

use App\Support\Month;
use Illuminate\View\View;
use InvalidArgumentException;
use Laravel\Head\Facades\Head;

/**
 * The editor page for the viewer's own memory of one month. The route only
 * lets participants in; the editor itself is a Livewire component, which
 * also checks that the month is open.
 */
class MemoryEditorController extends Controller
{
    public function __invoke(string $month): View
    {
        try {
            $month = Month::parseYearMonth($month);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        // viewport-fit=cover lets the sticky footer sit clear of the iPhone home indicator.
        Head::title("Your {$month->label()}")
            ->viewport('width=device-width, initial-scale=1, viewport-fit=cover');

        return view('memories.write', ['month' => $month]);
    }
}
