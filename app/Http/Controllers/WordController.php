<?php

namespace App\Http\Controllers;

use App\Enum\LearningStatus;
use App\Enum\WordSort;
use App\Models\Flashcard;
use App\Models\Word;
use App\Queries\ListWordsQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Head\Facades\Head;

class WordController extends Controller
{
    private const int DEFAULT_PER_PAGE = 250;

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::enum(WordSort::class)],
            'status' => ['nullable', Rule::enum(LearningStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            // Far past any real list, and low enough that the page's offset
            // can't overflow an integer.
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $search = trim($validated['q'] ?? '');
        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);
        $pageSize = $perPage === self::DEFAULT_PER_PAGE ? null : $perPage;

        // A search is its own mode: a lookup ranked by best match. The status
        // filters and sorts are for browsing, so a search leaves them out.
        $query = $search !== ''
            ? new ListWordsQuery($search)
            : new ListWordsQuery(
                sort: WordSort::tryFrom($validated['sort'] ?? ''),
                status: LearningStatus::tryFrom($validated['status'] ?? ''),
            );
        $words = $query
            ->paginate(perPage: $perPage, page: (int) ($validated['page'] ?? 1))
            ->withQueryString();

        // The list's search or browsing state and page size, without defaults so URLs stay short.
        $listState = array_filter([
            'q' => $search,
            'status' => $query->status?->value,
            'sort' => $query->sort === WordSort::defaultFor($query->isSearching()) ? null : $query->sort->value,
            'per_page' => $pageSize,
        ]);

        return view('words.index', [
            'words' => $words,
            'dueCount' => Flashcard::query()->dueNow()->count(),
            'search' => $search,
            'sort' => $query->sort,
            'status' => $query->status,
            'statusLinks' => $query->isSearching() ? [] : $this->statusLinks($query, $listState),
            'sortLinks' => $query->isSearching() ? [] : array_map(fn (WordSort $option): array => [
                'label' => $option->label(),
                'sort' => $option,
                'url' => $this->listUrl($listState, ['sort' => $option === WordSort::Recent ? null : $option->value]),
            ], [WordSort::Recent, WordSort::AToZ]),
            // The search box and Clear search start afresh, keeping only the page size.
            'searchHiddenFields' => array_filter(['per_page' => $pageSize]),
            'clearSearchUrl' => $this->listUrl(['per_page' => $pageSize], []),
            'firstPageUrl' => $this->listUrl($listState, []),
            // Headings like a dictionary's only make sense in an A to Z list.
            // A search has its own headings, for its two kinds of match.
            'showsLetterHeadings' => $query->sort === WordSort::AToZ,
            'showsMatchHeadings' => $query->isSearching(),
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
     * The list page David came from, keeping his search, sort and page, or
     * the plain list when he arrived some other way.
     */
    private function backUrl(Request $request): string
    {
        $session = $request->session();

        return $session->previousRoute() === 'words.index' ? $session->previousUrl() : route('words.index');
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
