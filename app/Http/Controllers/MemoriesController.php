<?php

namespace App\Http\Controllers;

use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Queries\ListMemoryMonthsQuery;
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

        return view('memories.index', [
            'monthsForViewer' => (new ListMemoryMonthsQuery)->execute()
                ->map(fn (Month $month) => new MonthForViewer($month, $viewer, $other)),
        ]);
    }

    /**
     * One month, one person per page: the other participant's memory by
     * default, or the viewer's own with `?who=yours`. Previous and next
     * step to the nearest months on the list.
     */
    public function show(Request $request, Month $month): View
    {
        $calendarMonth = $month->calendarMonth;
        $listedMonths = new ListMemoryMonthsQuery;

        Head::title($calendarMonth->label());

        $viewer = $request->user();

        return view('memories.show', [
            'monthForViewer' => new MonthForViewer($month, $viewer, MemoryParticipant::otherThan($viewer)),
            'showingYours' => $request->query('who') === 'yours',
            'previous' => $listedMonths->previous($calendarMonth),
            'next' => $listedMonths->next($calendarMonth),
        ]);
    }
}
