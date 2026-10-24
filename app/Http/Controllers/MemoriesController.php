<?php

namespace App\Http\Controllers;

use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Queries\ListOpenMonthsQuery;
use App\Support\CalendarMonth;
use App\View\Memories\MonthForViewer;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Laravel\Head\Facades\Head;

/**
 * The read side of monthly memories: the list of months, and one month as
 * the viewer sees it. The routes only let participants in.
 */
class MemoriesController extends Controller
{
    public function index(Request $request): View
    {
        $viewer = $request->user();
        $other = MemoryParticipant::otherThan($viewer);

        return view('memories.index', [
            'monthsForViewer' => (new ListOpenMonthsQuery)->execute()
                ->map(fn (Month $month) => new MonthForViewer($month, $viewer, $other)),
        ]);
    }

    /**
     * One month, one person per page: the other participant's memory by
     * default, or the viewer's own with `?who=yours`.
     */
    public function show(Request $request, string $month): View
    {
        try {
            $month = CalendarMonth::parseYearMonth($month);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        $openMonths = new ListOpenMonthsQuery;
        abort_unless($openMonths->contains($month), 404);

        Head::title($month->label());

        $viewer = $request->user();

        return view('memories.show', [
            'monthForViewer' => new MonthForViewer(Month::of($month), $viewer, MemoryParticipant::otherThan($viewer)),
            'showingYours' => $request->query('who') === 'yours',
            'previous' => $openMonths->contains($month->previous()) ? $month->previous() : null,
            'next' => $openMonths->contains($month->next()) ? $month->next() : null,
        ]);
    }
}
