<div>
    <article>
        <p>
            <a
                class="link {{ $note->title ? "" : "link link-hover" }}"
                href="{{ route("notes.show", $note) }}"
            >
                @if ($note->title)
                    <span class="font-semibold">{{ $note->title }}</span>
                @else
                    <span class="text-sm text-gray-600">Permalink</span>
                @endif
            </a>
            @if (! $note->visible)
                <span class="badge badge-ghost ml-2">Unpublished</span>
            @endif
        </p>

        @if ($note->lead)
            <p>{{ $note->lead }}</p>
        @endif
    </article>
    <div class="text-sm text-gray-600">
        {{ $note->publicationDate() }}
    </div>
</div>
