<x-words.page
    :due-count="$dueCount"
    :search="$search"
    :page-size="$pageSize"
    :transition-direction="$transitionDirection"
>
    <x-slot:summary>
        {{ trans_choice(':count match|:count matches', $words->total(), ['count' => number_format($words->total())]) }} for
        “{{ $search }}” ·
        <a href="{{ $clearSearchUrl }}" class="link tap-target">Clear search</a>
    </x-slot:summary>

    @if ($words->isEmpty())
        <p class="text-base-content/70 py-8 text-center">
            @if ($words->total() > 0)
                No words on this page.
                <a href="{{ $firstPageUrl }}" class="link">Back to the first page</a>
            @else
                No words match “{{ $search }}”.
            @endif
        </p>
    @else
        <x-words.list :words="$words" shows-match-headings />
        <x-words.pagination :words="$words" />
    @endif
</x-words.page>
