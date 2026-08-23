<div class="flex gap-4">
    <div class="text-base-content/60 w-16 shrink-0 text-sm">{{ $note->published_at->format("M j") }}</div>
    <article>
        <p>
            <a class="link-hover" href="{{ route("notes.show", $note) }}">
                @if ($note->title)
                    {{ $note->title }}
                @else
                    <span class="text-base-content/60 text-sm">Permalink</span>
                @endif
            </a>
            @if (! $note->visible)
                <span class="badge badge-ghost ml-2">Unpublished</span>
            @endif
        </p>

        @if ($note->lead)
            <p class="text-base-content/70 text-sm">{{ $note->lead }}</p>
        @endif
    </article>
</div>
