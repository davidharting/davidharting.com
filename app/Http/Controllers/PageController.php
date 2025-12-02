<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        $query = Page::query()->orderBy('updated_at', 'desc');

        if (! auth()->user()?->can('viewAny', Page::class)) {
            $query->where('is_published', true);
        }

        return view('pages.index', [
            'pages' => $query->get(),
        ]);
    }

    public function show(Page $page): View
    {
        return view('pages.show', [
            'page' => $page,
        ]);
    }
}
