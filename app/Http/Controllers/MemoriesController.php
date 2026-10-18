<?php

namespace App\Http\Controllers;

use App\Models\MemoryParticipant;
use App\Queries\ListMemoryMonthsQuery;
use App\Support\MemoryMonth;
use App\Support\Month;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use InvalidArgumentException;
use Laravel\Head\Facades\Head;

/**
 * The read side of monthly memories: the list of months, and one month as
 * the viewer sees it. The `read-memories` gate on the routes has already
 * turned away everyone who isn't a participant.
 */
class MemoriesController extends Controller
{
    public function index(Request $request): View
    {
        return view('memories.index', [
            'months' => $this->months($request),
        ]);
    }

    /**
     * One month, one person per page: the other participant's memory by
     * default, or the viewer's own with `?who=yours`.
     */
    public function show(Request $request, string $month): View
    {
        try {
            $wanted = Month::parseYearMonth($month);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        $months = $this->months($request);
        $index = $months->search(fn (MemoryMonth $candidate) => $candidate->month->equals($wanted));
        abort_if($index === false, 404);

        Head::title($wanted->label());

        return view('memories.show', [
            'memoryMonth' => $months[$index],
            'newer' => $months[$index - 1] ?? null,
            'older' => $months[$index + 1] ?? null,
            'showingYours' => $request->query('who') === 'yours',
        ]);
    }

    /**
     * @return Collection<int, MemoryMonth>
     */
    private function months(Request $request): Collection
    {
        $viewer = $request->user();

        return (new ListMemoryMonthsQuery($viewer, MemoryParticipant::otherThan($viewer)))->execute();
    }
}
