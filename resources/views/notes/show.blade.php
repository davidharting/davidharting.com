<x-layout.app :title="$note->title" :description="$description">
    <div class="max-w-3xl mx-auto">
        <header class="text-center mb-8">
            <p class="text-sm text-base-content/60 mb-2">
                {{ $note->publicationDate() }}
            </p>
            @if ($note->title)
                <h1 class="text-3xl font-bold">{{ $note->title }}</h1>
            @endif

            @if ($note->lead)
                <p class="text-lg text-base-content/70 mt-4">
                    {{ $note->lead }}
                </p>
            @endif
        </header>

        @if ($note->renderContent())
            <article class="prose prose-lg max-w-none">
                {!! $note->renderContent() !!}
            </article>
        @endif

        <p class="text-sm mt-8">
            <a href="{{ route("notes.index") }}" class="link link-primary">
                Back to all notes
            </a>
        </p>
    </div>
</x-layout.app>
