<?php

namespace App\Http\Controllers;

use App\Enum\WordSort;
use App\Queries\ListWordsQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WordController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim($validated['q'] ?? '');
        $sort = $request->enum('sort', WordSort::class) ?? WordSort::Recent;

        $words = (new ListWordsQuery($search, $sort))
            ->paginate(perPage: 100, page: max(1, $request->integer('page', 1)))
            ->withQueryString();

        return view('words.index', [
            'words' => $words,
            'search' => $search,
            'sort' => $sort,
        ]);
    }
}
