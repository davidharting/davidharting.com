<div class="flex gap-4">
    <div class="w-16 shrink-0 text-sm text-base-content/60">
        {{ $note->published_at->format("M j") }}
    </div>
    <article>
        <p>
            <a class="link-hover" href="{{ route("notes.show", $note) }}">
                @if ($note->title)
                    {{ $note->title }}
                @else
                    <span class="text-sm text-base-content/60">Permalink</span>
                @endif
            </a>
            @if (! $note->visible)
                <span class="badge badge-ghost ml-2">Unpublished</span>
            @endif
        </p>

        @if ($note->lead)
            <p class="text-sm text-base-content/70">{{ $note->lead }}</p>
        @endif
    </article>
</div>
