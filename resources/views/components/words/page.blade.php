{{--
    The frame the word list and a word search share: the title, the Practise button, a one-line summary and the search box.
--}}
@props(['dueCount', 'summary', 'search' => '', 'pageSize' => null, 'transitionDirection' => null])

<x-layout.app cross-document-transitions :transition-direction="$transitionDirection">
    <div class="mx-auto max-w-2xl">
        <header class="mb-6">
            <div class="flex items-center justify-between gap-4">
                <x-type.page-title>Words</x-type.page-title>
                <a href="{{ route("words.practice") }}" class="btn btn-primary btn-sm">
                    Practise
                    @if ($dueCount > 0)
                        <span class="badge badge-sm">{{ number_format($dueCount) }} due</span>
                    @else
                        <span class="badge badge-sm badge-ghost">caught up</span>
                    @endif
                </a>
            </div>
            <p class="text-base-content/70 mt-2 text-sm">{{ $summary }}</p>
        </header>

        <form method="GET" action="{{ route("words.search") }}" role="search" class="mb-4 flex gap-2">
            @if ($pageSize)
                <input type="hidden" name="per_page" value="{{ $pageSize }}" />
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

        {{ $slot }}
    </div>
</x-layout.app>
