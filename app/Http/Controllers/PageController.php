<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;
use Laravel\Head\Facades\Head;
use Laravel\Head\Facades\Schema;

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
        Head::title($page->title)
            ->og(title: $page->title)
            ->twitter(title: $page->title)
            ->unless($page->is_published, fn ($head) => $head->hiddenFromRobots())
            ->schema(Schema::breadcrumbs()->items([
                'Home' => route('home'),
                'Pages' => route('pages.index'),
                $page->title => route('pages.show', $page),
            ]));

        return view('pages.show', [
            'page' => $page,
        ]);
    }
}
