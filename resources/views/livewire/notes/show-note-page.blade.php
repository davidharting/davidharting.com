<div>
    @if ($note->title)
        <x-type.page-title>{{ $note->title }}</x-type.page-title>
    @endif

    @if ($note->lead)
        <p class="text-3xl mt-1">{{ $note->lead }}</p>
    @endif

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
