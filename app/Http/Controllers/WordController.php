<?php

namespace App\Http\Controllers;

use App\Enum\WordSort;
use App\Models\Word;
use App\Queries\ListWordsQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            // Far past any real list, and low enough that the page's offset
            // can't overflow an integer.
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $search = trim($validated['q'] ?? '');
        $sort = WordSort::tryFrom($validated['sort'] ?? '') ?? WordSort::Recent;
        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);

        $words = (new ListWordsQuery($search, $sort))
            ->paginate(perPage: $perPage, page: (int) ($validated['page'] ?? 1))
            ->withQueryString();

        // The list's search, sort and page size, without defaults so URLs stay short.
        $listState = array_filter([
            'q' => $search,
            'sort' => $sort === WordSort::Recent ? null : $sort->value,
            'per_page' => $perPage === self::DEFAULT_PER_PAGE ? null : $perPage,
        ]);

        return view('words.index', [
            'words' => $words,
            'search' => $search,
            'sort' => $sort,
            // The search box replaces q, and keeps the rest of the list's state.
            'searchHiddenFields' => Arr::except($listState, 'q'),
            'sortLinks' => [
                ['label' => 'Recent', 'sort' => WordSort::Recent, 'url' => $this->listUrl($listState, ['sort' => null])],
                ['label' => 'A to Z', 'sort' => WordSort::AToZ, 'url' => $this->listUrl($listState, ['sort' => WordSort::AToZ->value])],
            ],
            'clearSearchUrl' => $this->listUrl($listState, ['q' => null]),
            'firstPageUrl' => $this->listUrl($listState, []),
            // Headings like a dictionary's only make sense in an A to Z list.
            'showsLetterHeadings' => $sort === WordSort::AToZ,
        ]);
    }

    public function show(Word $word): View
    {
        Head::title($word->word)->description($word->short_definition);

        return view('words.show', [
            'word' => $word,
            'backUrl' => $this->backUrl(),
        ]);
    }

    /**
     * The list page David came from, keeping his search, sort and page, or
     * the plain list when he arrived some other way.
     */
    private function backUrl(): string
    {
        $previous = url()->previous();

        return Str::before($previous, '?') === route('words.index') ? $previous : route('words.index');
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
