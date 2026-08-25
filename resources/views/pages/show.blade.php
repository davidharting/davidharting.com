<x-layout.app>
    <div class="mx-auto max-w-3xl">
        <header class="mb-8 text-center">
            <h1 class="text-3xl font-bold">{{ $page->title }}</h1>
        </header>

        @if ($page->renderContent())
            <article class="prose prose-lg max-w-none">{!! $page->renderContent() !!}</article>
        @endif

        <p class="mt-8 text-sm">
            <a href="{{ route("pages.index") }}" class="link link-primary"> Back to all pages </a>
        </p>
    </div>
</x-layout.app>
