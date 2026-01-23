<x-layout.app :title="$note->title" :description="$description">
    <article class="prose dark:prose-invert">
        <p class="text-sm text-gray-600">
            {{ $note->publicationDate() }}
        </p>

        <x-notes.prose :note="$note" />

        <p class="text-sm">
            <a href="{{ route("notes.index") }}" class="link">
                Back to all notes
            </a>
        </p>
    </article>
</x-layout.app>
