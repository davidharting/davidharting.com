{{--
    In an A to Z list, a letter heading starts each run of words filed under the same letter, like a dictionary's.
    In a search, a heading starts the words whose spelling matches, and another the words found only in a definition.
--}}
@props(['words', 'showsLetterHeadings' => false, 'showsMatchHeadings' => false])

@php
    $currentLetter = null;
    $currentMatch = null;
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

        @if ($showsMatchHeadings && $word->matches_word !== $currentMatch)
            @php
                $currentMatch = $word->matches_word;
            @endphp

            <li class="border-base-300 bg-base-100 sticky top-0 z-10 border-b pt-4 pb-1">
                <h2 class="text-base-content/70 text-sm font-semibold tracking-wide uppercase">
                    {{ $currentMatch ? "Words" : "Found in definitions" }}
                </h2>
            </li>
        @endif

        <li class="list-row p-0">
            <a href="{{ route("words.show", $word) }}" class="block px-1 py-3">
                <span class="block text-xl font-semibold wrap-anywhere">{{ $word->word }}</span>
                <span class="text-base-content/70 line-clamp-2 block">{{ $word->short_definition }}</span>
            </a>
        </li>
    @endforeach
</ul>
