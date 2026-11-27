<?php

namespace App\Http\Controllers;

use App\Enum\LearningStatus;
use App\Enum\WordSort;
use App\Models\Flashcard;
use App\Models\Word;
use App\Queries\ListWordsQuery;
use App\Queries\SearchWordsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Head\Facades\Head;

class WordController extends Controller
{
    private const int DEFAULT_PER_PAGE = 250;

    /**
     * Paging rules both the list and a search take.
     */
    private const array PAGE_RULES = [
        'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        // Far past any real list, and low enough that the page's offset
        // can't overflow an integer.
        'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
    ];

    /**
     * Browse the list, with status filters and sorts. A search has its own
     * page, search().
     */
    public function index(Request $request): View|RedirectResponse
    {
        // Older links searched here.
        if ($request->filled('q')) {
            return redirect()->route('words.search', $request->only(['q', 'per_page']));
        }

        $validated = $request->validate([
            'sort' => ['nullable', Rule::enum(WordSort::class)],
            'status' => ['nullable', Rule::enum(LearningStatus::class)],
            ...self::PAGE_RULES,
        ]);

        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);
        $query = new ListWordsQuery(
            sort: WordSort::tryFrom($validated['sort'] ?? '') ?? WordSort::Recent,
            status: LearningStatus::tryFrom($validated['status'] ?? ''),
        );

        // The list's status, sort and page size, without defaults so URLs stay short.
        $listState = array_filter([
            'status' => $query->status?->value,
            'sort' => $query->sort === WordSort::Recent ? null : $query->sort->value,
            'per_page' => $perPage === self::DEFAULT_PER_PAGE ? null : $perPage,
        ]);

        return view('words.index', [
            'words' => $query->paginate(perPage: $perPage, page: (int) ($validated['page'] ?? 1))->appends($listState),
            'dueCount' => Flashcard::query()->dueNow()->count(),
            'sort' => $query->sort,
            'status' => $query->status,
            'statusLinks' => $this->statusLinks($query, $listState),
            'sortLinks' => [
                ['label' => 'Recent', 'sort' => WordSort::Recent, 'url' => $this->listUrl($listState, ['sort' => null])],
                ['label' => 'A to Z', 'sort' => WordSort::AToZ, 'url' => $this->listUrl($listState, ['sort' => WordSort::AToZ->value])],
            ],
            'pageSize' => $listState['per_page'] ?? null,
            'firstPageUrl' => $this->listUrl($listState, []),
            // Headings like a dictionary's only make sense in an A to Z list.
            'showsLetterHeadings' => $query->sort === WordSort::AToZ,
            'transitionDirection' => $this->cameFromAWord($request) ? 'back' : null,
        ]);
    }

    /**
     * Look words up, ranked by best match. Browsing is index().
     */
    public function search(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            ...self::PAGE_RULES,
        ]);

        $search = trim($validated['q'] ?? '');
        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);
        $pageSize = $perPage === self::DEFAULT_PER_PAGE ? null : $perPage;

        if ($search === '') {
            return redirect()->route('words.index', array_filter(['per_page' => $pageSize]));
        }

        $searchState = array_filter(['q' => $search, 'per_page' => $pageSize]);

        return view('words.search', [
            'words' => (new SearchWordsQuery($search))
                ->paginate(perPage: $perPage, page: (int) ($validated['page'] ?? 1))
                ->appends($searchState),
            'dueCount' => Flashcard::query()->dueNow()->count(),
            'search' => $search,
            'pageSize' => $pageSize,
            'clearSearchUrl' => $this->listUrl(array_filter(['per_page' => $pageSize]), []),
            'firstPageUrl' => route('words.search', $searchState),
            'transitionDirection' => $this->cameFromAWord($request) ? 'back' : null,
        ]);
    }

    public function show(Request $request, Word $word): View
    {
        Head::title($word->word)->description($word->short_definition);

        return view('words.show', [
            'word' => $word,
            'status' => $word->flashcard->learning_status,
            'reviews' => $word->reviews()->orderBy('reviewed_at', 'desc')->orderBy('reviews.id', 'desc')->get(),
            'backUrl' => $this->backUrl($request),
        ]);
    }

    /**
     * Whether David is coming back to the list from a word's page, so the
     * list can turn the page back instead of forward.
     */
    private function cameFromAWord(Request $request): bool
    {
        return $request->session()->previousRoute() === 'words.show';
    }

    /**
     * The list or search page David came from, keeping his search, sort and
     * page, or the plain list when he arrived some other way.
     */
    private function backUrl(Request $request): string
    {
        $session = $request->session();

        return in_array($session->previousRoute(), ['words.index', 'words.search'], true) ? $session->previousUrl() : route('words.index');
    }

    /**
     * Every word, then each status, with counts, so a tap narrows the list.
     *
     * @param  array<string, string|int>  $listState
     * @return list<array{label: string, count: int, active: bool, url: string}>
     */
    private function statusLinks(ListWordsQuery $query, array $listState): array
    {
        $countsByStatus = $query->countsByStatus();

        return [
            ['label' => 'All', 'count' => array_sum($countsByStatus), 'active' => $query->status === null, 'url' => $this->listUrl($listState, ['status' => null])],
            ...array_map(fn (LearningStatus $option): array => [
                'label' => $option->label(),
                'count' => $countsByStatus[$option->value],
                'active' => $query->status === $option,
                'url' => $this->listUrl($listState, ['status' => $option->value]),
            ], LearningStatus::cases()),
        ];
    }

    /**
     * @param  array<string, string|int>  $listState
     * @param  array<string, string|int|null>  $changes
     */
    private function listUrl(array $listState, array $changes): string
    {
        return route('words.index', array_filter([...$listState, ...$changes]));
    }
}
