<x-words.page :due-count="$dueCount" :page-size="$pageSize" :transition-direction="$transitionDirection">
    <x-slot:summary>
        {{ trans_choice(':count word|:count words', $words->total(), ['count' => number_format($words->total())]) }}
        @if ($status)
            · {{ $status->label() }}
        @endif
    </x-slot:summary>

    <nav aria-label="Filter by learning status" class="mb-4 flex flex-wrap gap-3">
        @foreach ($statusLinks as $link)
            <a
                href="{{ $link["url"] }}"
                class="btn btn-sm tap-target {{ $link["active"] ? "btn-primary" : "btn-outline" }}"
                @if ($link['active']) aria-current="true" @endif
            >
                {{ $link["label"] }}
                <span class="badge badge-sm">{{ number_format($link["count"]) }}</span>
            </a>
        @endforeach
    </nav>

    <div class="join mb-6" role="group" aria-label="Sort">
        @foreach ($sortLinks as $link)
            <a
                href="{{ $link["url"] }}"
                class="btn btn-sm join-item tap-target {{ $sort === $link["sort"] ? "btn-active" : "" }}"
                @if ($sort === $link['sort']) aria-current="true" @endif
            >
                {{ $link["label"] }}
            </a>
        @endforeach
    </div>

    @if ($words->isEmpty())
        <p class="text-base-content/70 py-8 text-center">
            @if ($words->total() > 0)
                No words on this page.
                <a href="{{ $firstPageUrl }}" class="link">Back to the first page</a>
            @elseif ($status)
                No {{ Str::lower($status->label()) }} words yet.
            @else
                No words yet.
            @endif
        </p>
    @else
        <x-words.list :words="$words" :shows-letter-headings="$showsLetterHeadings" />
        <x-words.pagination :words="$words" />
    @endif
</x-words.page>
