<div>
    @if ($note->title)
        <h1 class="font-serif">{{ $note->title }}</h1>
    @endif

    @if ($note->lead)
        <p class="lead">{{ $note->lead }}</p>
    @endif

    @if ($note->renderContent())
        {!! $note->renderContent() !!}
    @endif
</div>
