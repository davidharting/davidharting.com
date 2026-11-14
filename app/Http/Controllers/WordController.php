<?php

namespace App\Http\Controllers;

use App\Enum\WordSort;
use App\Models\Word;
use App\Queries\ListWordsQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WordController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $search = trim($validated['q'] ?? '');
        $sort = WordSort::tryFrom($validated['sort'] ?? '') ?? WordSort::Recent;

        $words = (new ListWordsQuery($search, $sort))
            ->paginate(perPage: 100, page: (int) ($validated['page'] ?? 1))
            ->withQueryString();

        return view('words.index', [
            'words' => $words,
            'search' => $search,
            'sort' => $sort,
            // Headings like a dictionary's, only when the list is alphabetical.
            'letterGroups' => $sort === WordSort::AToZ
                ? $words->getCollection()->groupBy(fn (Word $word): string => $word->dictionaryLetter())
                : null,
        ]);
    }
}
