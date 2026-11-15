{{--
    In an A to Z list, a letter heading starts each run of words filed under the same letter, like a dictionary's.

    Every row's word carries a view-transition name so it grows into the detail
    page's headword and shrinks back into its row. Naming only the tapped row
    would need pagereveal to run before first paint, which Vite's deferred
    scripts can't promise, so every row is named; small text snapshots are cheap.
--}}
@php
    $currentLetter = null;
@endphp

<ul class="list">
    @foreach ($words as $word)
        @if ($showsLetterHeadings && $word->dictionaryLetter() !== $currentLetter)
            @php
                $currentLetter = $word->dictionaryLetter();
            @endphp

            <li class="border-base-300 bg-base-100 sticky top-0 z-10 border-b pt-4 pb-1">
                <h2 class="text-3xl font-bold">{{ $currentLetter }}</h2>
            </li>
        @endif

        <li class="list-row p-0">
            <a href="{{ route("words.show", $word) }}" class="block px-1 py-3">
                <span
                    class="headword-name inline-block max-w-full text-xl font-semibold wrap-anywhere"
                    style="view-transition-name: headword-{{ $word->id }}; view-transition-class: headword"
                >
                    {{ $word->word }}
                </span>
                <span class="text-base-content/70 line-clamp-2 block">{{ $word->short_definition }}</span>
            </a>
        </li>
    @endforeach
</ul>
