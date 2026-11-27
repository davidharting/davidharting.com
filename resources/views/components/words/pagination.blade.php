{{--
    Previous and Next links for a page of words, when there is more than one page.
--}}
@props(['words'])

@if ($words->hasPages())
    <nav aria-label="Pages" class="my-8 flex items-center justify-between gap-2">
        @if ($words->onFirstPage())
            <span class="btn btn-sm btn-disabled" aria-disabled="true">Previous</span>
        @else
            <a href="{{ $words->previousPageUrl() }}" class="btn btn-sm tap-target" rel="prev">Previous</a>
        @endif

        <span class="text-base-content/70 text-sm">Page {{ $words->currentPage() }} of {{ $words->lastPage() }}</span>

        @if ($words->hasMorePages())
            <a href="{{ $words->nextPageUrl() }}" class="btn btn-sm tap-target" rel="next">Next</a>
        @else
            <span class="btn btn-sm btn-disabled" aria-disabled="true">Next</span>
        @endif
    </nav>
@endif
