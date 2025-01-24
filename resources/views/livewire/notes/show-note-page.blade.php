<x-slot:title>{{ $note->title }}</x-slot>

<x-slot:description>
    {{ $this->description() }}
</x-slot>

<div>
    @if ($note->title)
        <x-type.page-title>{{ $note->title }}</x-type.page-title>
    @endif

    @if ($note->lead)
        <p class="text-2xl mt-2">{{ $note->lead }}</p>
    @endif

    <p class="mt-2 text-gray-600">
        {{ $note->publicationDate() }}
    </p>

    @if ($note->content)
        <div class="mt-4 prose">
            {!! $note->content !!}
        </div>
    @endif

    <div class="mt-6">
        <a href="{{ route("notes.index") }}" class="link" wire:navigate>
            Back to all notes
        </a>
    </div>
</div>
