<?php

namespace App\Http\Controllers;

use App\Enum\WordSort;
use App\Queries\Words\BrowseWordsQuery;
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

        $words = (new BrowseWordsQuery($search, $sort))->execute()->withQueryString();

        return view('words.index', [
            'words' => $words,
            'search' => $search,
            'sort' => $sort,
        ]);
    }
}
