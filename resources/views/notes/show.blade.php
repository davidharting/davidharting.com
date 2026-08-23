<x-layout.app :title="$note->title" :description="$description">
    <div class="mx-auto max-w-3xl">
        <header class="mb-8 text-center">
            <p class="text-base-content/60 mb-2 text-sm">{{ $note->publicationDate() }}</p>
            @if ($note->title)
                <h1 class="text-3xl font-bold">{{ $note->title }}</h1>
            @endif

            @if ($note->lead)
                <p class="text-base-content/70 mt-4 text-lg">{{ $note->lead }}</p>
            @endif
        </header>

        @if ($note->renderContent())
            <article class="prose prose-lg max-w-none">{!! $note->renderContent() !!}</article>
        @endif

        <p class="mt-8 text-sm">
            <a href="{{ route("notes.index") }}" class="link link-primary"> Back to all notes </a>
        </p>
    </div>
</x-layout.app>
