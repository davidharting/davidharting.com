<x-layout.app :title="$page->title" :description="$page->title">
    <div class="max-w-3xl mx-auto">
        <header class="text-center mb-8">
            <h1 class="text-3xl font-bold">{{ $page->title }}</h1>
        </header>

        @if ($page->renderContent())
            <article class="prose prose-lg max-w-none">
                {!! $page->renderContent() !!}
            </article>
        @endif

        <p class="text-sm mt-8">
            <a href="{{ route("pages.index") }}" class="link link-primary">
                Back to all pages
            </a>
        </p>
    </div>
</x-layout.app>
