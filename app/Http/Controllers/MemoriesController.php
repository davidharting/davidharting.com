<?php

namespace App\Http\Controllers;

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Queries\ListOpenMonthsQuery;
use App\Support\Month;
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
        $months = (new ListOpenMonthsQuery)->execute();

        $memoriesByMonth = Memory::query()
            ->whereBetween('month', [$months->last()->toDateString(), $months->first()->toDateString()])
            ->get()
            ->groupBy(fn (Memory $memory) => $memory->month->toDateString());

        return view('memories.index', [
            'months' => $months,
            'memoriesByMonth' => $memoriesByMonth,
            'viewer' => $request->user(),
            'other' => MemoryParticipant::otherThan($request->user()),
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

        return view('memories.show', [
            'month' => $month,
            'memories' => Memory::query()->where('month', $month->toDateString())->get(),
            'viewer' => $request->user(),
            'other' => MemoryParticipant::otherThan($request->user()),
            'showingYours' => $request->query('who') === 'yours',
            'older' => $openMonths->contains($month->previous()) ? $month->previous() : null,
            'newer' => $openMonths->contains($month->next()) ? $month->next() : null,
        ]);
    }
}
