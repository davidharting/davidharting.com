<div>
    <article>
        <p>
            <a
                class="link {{ $note->title ? "" : "link link-hover" }}"
                wire:navigate
                href="{{ route("notes.show", $note) }}"
            >
                @if ($note->title)
                    <span class="font-semibold">{{ $note->title }}</span>
                @else
                    <span class="text-sm text-gray-600">Permalink</span>
                @endif
            </a>
        </p>

        @if ($note->lead)
            <p>{{ $note->lead }}</p>
        @endif
    </article>
    <div class="text-sm text-gray-600">
        {{ \Carbon\Carbon::parse($note->published_at)->format("Y F j") }}
    </div>
</div>
