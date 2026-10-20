<?php

namespace App\Http\Controllers;

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Queries\ListOpenMonthsQuery;
use App\Support\Month;
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
        $months = (new ListOpenMonthsQuery)->execute();

        $memoriesByMonth = Memory::query()
            ->whereBetween('month', [$months->last()->toDateString(), $months->first()->toDateString()])
            ->get()
            ->groupBy(fn (Memory $memory) => $memory->month->toDateString());

        return view('memories.index', [
            'monthsForViewer' => $months->map(fn (Month $month) => MonthForViewer::fromMemories(
                $month,
                $viewer,
                $other,
                $memoriesByMonth->get($month->toDateString(), collect()),
            )),
        ]);
    }

    /**
     * One month, one person per page: the other participant's memory by
     * default, or the viewer's own with `?who=yours`.
     */
    public function show(Request $request, string $month): View
    {
        try {
            $month = Month::parseYearMonth($month);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        $openMonths = new ListOpenMonthsQuery;
        abort_unless($openMonths->contains($month), 404);

        Head::title($month->label());

        $viewer = $request->user();

        return view('memories.show', [
            'monthForViewer' => MonthForViewer::fromMemories(
                $month,
                $viewer,
                MemoryParticipant::otherThan($viewer),
                Memory::query()->where('month', $month->toDateString())->get(),
            ),
            'showingYours' => $request->query('who') === 'yours',
            'previous' => $openMonths->contains($month->previous()) ? $month->previous() : null,
            'next' => $openMonths->contains($month->next()) ? $month->next() : null,
        ]);
    }
}
