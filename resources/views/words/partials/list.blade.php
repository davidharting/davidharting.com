{{-- In an A to Z list, a letter heading starts each run of words filed under the same letter, like a dictionary's. --}}
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
            <div class="px-1 py-3">
                <span class="block text-xl font-semibold">{{ $word->word }}</span>
                <span class="text-base-content/70 line-clamp-2 block">{{ $word->short_definition }}</span>
            </div>
        </li>
    @endforeach
</ul>
