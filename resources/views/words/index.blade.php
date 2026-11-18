<x-layout.app cross-document-transitions :transition-direction="$transitionDirection">
    <div class="mx-auto max-w-2xl">
        <header class="mb-6">
            <x-type.page-title>Words</x-type.page-title>
            <p class="text-base-content/70 mt-2 text-sm">
                @if ($search !== '')
                    {{ trans_choice(':count match|:count matches', $words->total(), ['count' => number_format($words->total())]) }}
                    for “{{ $search }}”
                @else
                    {{ trans_choice(':count word|:count words', $words->total(), ['count' => number_format($words->total())]) }}
                @endif
            </p>
        </header>

        <form method="GET" action="{{ route("words.index") }}" role="search" class="mb-4 flex gap-2">
            @foreach ($searchHiddenFields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}" />
            @endforeach

            <label class="input w-full">
                <span class="sr-only">Search words and definitions</span>
                <input
                    type="search"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Search words and definitions"
                    maxlength="255"
                    enterkeyhint="search"
                    autocapitalize="none"
                    autocomplete="off"
                    spellcheck="false"
                />
            </label>
            <button type="submit" class="btn">Search</button>
        </form>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-2">
            <div class="join" role="group" aria-label="Sort">
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

            @if ($search !== '')
                <a href="{{ $clearSearchUrl }}" class="link link-hover tap-target text-sm">Clear search</a>
            @endif
        </div>

        @if ($words->isEmpty())
            <p class="text-base-content/70 py-8 text-center">
                @if ($words->total() > 0)
                    No words on this page.
                    <a href="{{ $firstPageUrl }}" class="link">Back to the first page</a>
                @elseif ($search !== '')
                    No words match “{{ $search }}”.
                @else
                    No words yet.
                @endif
            </p>
        @else
            <x-words.list :words="$words" :shows-letter-headings="$showsLetterHeadings" />

            @if ($words->hasPages())
                <nav aria-label="Pages" class="my-8 flex items-center justify-between gap-2">
                    @if ($words->onFirstPage())
                        <span class="btn btn-sm btn-disabled" aria-disabled="true">Previous</span>
                    @else
                        <a href="{{ $words->previousPageUrl() }}" class="btn btn-sm tap-target" rel="prev">Previous</a>
                    @endif

                    <span class="text-base-content/70 text-sm">
                        Page {{ $words->currentPage() }} of {{ $words->lastPage() }}
                    </span>

                    @if ($words->hasMorePages())
                        <a href="{{ $words->nextPageUrl() }}" class="btn btn-sm tap-target" rel="next">Next</a>
                    @else
                        <span class="btn btn-sm btn-disabled" aria-disabled="true">Next</span>
                    @endif
                </nav>
            @endif
        @endif
    </div>
</x-layout.app>
