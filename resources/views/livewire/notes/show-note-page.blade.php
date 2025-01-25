<x-slot:title>{{ $note->title }}</x-slot>

<x-slot:description>
    {{ $this->description() }}
</x-slot>

<article class="prose dark:prose-invert">
    <p class="text-sm text-gray-600">
        {{ $note->publicationDate() }}
    </p>

    @if ($note->title)
        <h1 class="font-serif">{{ $note->title }}</h1>
    @endif

    @if ($note->lead)
        <p class="lead">{{ $note->lead }}</p>
    @endif

    @if ($note->content)
        {!! $note->content !!}
    @endif

    <p class="text-sm">
        <a href="{{ route("notes.index") }}" class="link" wire:navigate>
            Back to all notes
        </a>
    </p>
</article>
