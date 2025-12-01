<x-layout.app :title="$page->title" :description="$page->title">
    <article class="prose dark:prose-invert">
        <h1 class="font-serif">{{ $page->title }}</h1>

        @if ($page->renderContent())
            {!! $page->renderContent() !!}
        @endif

        <p class="text-sm mt-8">
            <a href="{{ route("pages.index") }}" class="link">
                Back to all pages
            </a>
        </p>
    </article>
</x-layout.app>
