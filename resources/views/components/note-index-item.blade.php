<div>
    <article>
        @if ($note->title)
            <flux:link variant='ghost' href="{{ route('notes.show', $note) }}">
                {{ $note->title }}
            </flux:link>
        @else
            <flux:link variant='subtle' href="{{ route('notes.show', $note) }}">
                <span class='text-xs'>Permalink</span>
            </flux:link>
        @endif

        @if ($note->lead)
            <flux:text>{{ $note->lead }}</flux:text>
        @endif
    </article>

    <flux:text size='sm'>
        {{ $note->publicationDate() }}
    </flux:text>
</div>
