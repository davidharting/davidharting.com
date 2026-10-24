<?php

namespace App\Http\Controllers;

use App\Models\Month;
use Illuminate\View\View;
use Laravel\Head\Facades\Head;

/**
 * The editor page for the viewer's own memory of one month. The route only
 * lets in participants who may write in the month; the editor itself is a
 * Livewire component, which checks again on every request.
 */
class MemoryEditorController extends Controller
{
    public function __invoke(Month $month): View
    {
        // viewport-fit=cover lets the Share button sit clear of the iPhone home indicator;
        // resizes-content shrinks the page above the keyboard where the browser supports it.
        Head::title("Your {$month->calendarMonth->label()}")
            ->viewport('width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content');

        return view('memories.write', ['month' => $month->calendarMonth]);
    }
}
