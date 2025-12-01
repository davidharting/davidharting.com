<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        $pages = Page::where('is_published', true)
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('pages.index', [
            'pages' => $pages,
        ]);
    }

    public function show(Page $page): View
    {
        $this->authorize('view', $page);

        return view('pages.show', [
            'page' => $page,
        ]);
    }
}
