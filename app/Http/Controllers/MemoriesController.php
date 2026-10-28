<?php

namespace App\Http\Controllers;

use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Queries\ListOpenMonthsQuery;
use App\Queries\MonthPageVersionsQuery;
use App\View\Memories\MonthForViewer;
use Illuminate\Http\Request;
use Illuminate\View\View;
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

        $months = (new ListOpenMonthsQuery)->execute();

        return view('memories.index', [
            'monthsForViewer' => $months->map(fn (Month $month) => new MonthForViewer($month, $viewer, $other)),
            'pageVersions' => (new MonthPageVersionsQuery)->execute($months),
        ]);
    }

    /**
     * One month, one person per page: the other participant's memory by
     * default, or the viewer's own with `?who=yours`.
     */
    public function show(Request $request, Month $month): View
    {
        $calendarMonth = $month->calendarMonth;
        $openMonths = new ListOpenMonthsQuery;

        Head::title($calendarMonth->label());

        $viewer = $request->user();

        return view('memories.show', [
            'monthForViewer' => new MonthForViewer($month, $viewer, MemoryParticipant::otherThan($viewer)),
            'showingYours' => $request->query('who') === 'yours',
            'previous' => $openMonths->contains($calendarMonth->previous()) ? $calendarMonth->previous() : null,
            'next' => $openMonths->contains($calendarMonth->next()) ? $calendarMonth->next() : null,
        ]);
    }
}
