@use("App\Enum\WordSort")

@php
    $listUrl = fn (array $overrides) => route('words.index', array_filter([
        'q' => $search,
        'sort' => $sort === WordSort::Recent ? null : $sort->value,
        ...$overrides,
    ]));

    // Headings like a dictionary's, only when the list is alphabetical.
    $groups = $sort === WordSort::AToZ
        ? $words->getCollection()->groupBy(fn ($word) => $word->dictionaryLetter())
        : collect(['' => $words->getCollection()]);
@endphp

<x-layout.app>
    <div class="mx-auto max-w-2xl">
        <header class="mb-6">
            <x-type.page-title>Words</x-type.page-title>
            <p class="text-base-content/60 mt-2 text-sm">
                @if ($search !== '')
                    {{ trans_choice(':count match|:count matches', $words->total(), ['count' => number_format($words->total())]) }}
                    for “{{ $search }}”
                @else
                    {{ trans_choice(':count word|:count words', $words->total(), ['count' => number_format($words->total())]) }}
                @endif
            </p>
        </header>

        <form method="GET" action="{{ route("words.index") }}" role="search" class="mb-4 flex gap-2">
            @if ($sort !== WordSort::Recent)
                <input type="hidden" name="sort" value="{{ $sort->value }}" />
            @endif

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
                <a
                    href="{{ $listUrl(["sort" => null]) }}"
                    class="btn btn-sm join-item {{ $sort === WordSort::Recent ? "btn-active" : "" }}"
                    @if ($sort === WordSort::Recent) aria-current="true" @endif
                >
                    Recent
                </a>
                <a
                    href="{{ $listUrl(["sort" => WordSort::AToZ->value]) }}"
                    class="btn btn-sm join-item {{ $sort === WordSort::AToZ ? "btn-active" : "" }}"
                    @if ($sort === WordSort::AToZ) aria-current="true" @endif
                >
                    A to Z
                </a>
            </div>

            @if ($search !== '')
                <a href="{{ $listUrl(["q" => null]) }}" class="link link-hover text-sm">Clear search</a>
            @endif
        </div>

        @if ($words->isEmpty())
            <p class="text-base-content/70 py-8 text-center">
                @if ($search !== '')
                    No words match “{{ $search }}”.
                @else
                    No words yet.
                @endif
            </p>
        @else
            @foreach ($groups as $letter => $wordsUnderLetter)
                <section @if ($letter !== '') aria-labelledby="letter-{{ $loop->index }}" @endif>
                    @if ($letter !== '')
                        <h2
                            id="letter-{{ $loop->index }}"
                            class="border-base-300 bg-base-100 sticky top-0 z-10 border-b pt-4 pb-1 text-3xl font-bold"
                        >
                            {{ $letter }}
                        </h2>
                    @endif

                    <ul class="list">
                        @foreach ($wordsUnderLetter as $word)
                            <li class="list-row p-0">
                                <div class="px-1 py-3">
                                    <span class="block text-xl font-semibold">{{ $word->word }}</span>
                                    <span class="text-base-content/70 line-clamp-2 block">{{ $word->short_definition }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            @if ($words->hasPages())
                <nav aria-label="Pages" class="my-8 flex items-center justify-between gap-2">
                    @if ($words->onFirstPage())
                        <span class="btn btn-sm btn-disabled" aria-disabled="true">Previous</span>
                    @else
                        <a href="{{ $words->previousPageUrl() }}" class="btn btn-sm" rel="prev">Previous</a>
                    @endif

                    <span class="text-base-content/60 text-sm">
                        Page {{ $words->currentPage() }} of {{ $words->lastPage() }}
                    </span>

                    @if ($words->hasMorePages())
                        <a href="{{ $words->nextPageUrl() }}" class="btn btn-sm" rel="next">Next</a>
                    @else
                        <span class="btn btn-sm btn-disabled" aria-disabled="true">Next</span>
                    @endif
                </nav>
            @endif
        @endif
    </div>
</x-layout.app>
