<x-slot:title>{{ $note->title }}</x-slot>

<x-slot:description>
    {{ $this->description() }}
</x-slot>

<article class="prose dark:prose-invert">
    <p class="text-sm text-gray-600">
        {{ $note->publicationDate() }}
    </p>

    <x-notes.prose :note="$note" />

    <p class="text-sm">
        <a href="{{ route("notes.index") }}" class="link" wire:navigate>
            Back to all notes
        </a>
    </p>
</article>
