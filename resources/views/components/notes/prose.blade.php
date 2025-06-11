<div>
    @if ($note->title)
        <flux:heading size='xl' level='1' class="font-serif">{{ $note->title }}</flux:heading>
    @endif

    @if ($note->lead)
        <p class="lead">{{ $note->lead }}</p>
    @endif

    @if ($note->content)
        {!! $note->content !!}
    @endif
</div>
