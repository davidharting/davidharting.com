<x-layout.app title="David's Notes" description="Notes from David">
    <x-type.page-title>Notes</x-type.page-title>
    @if ($notes->isEmpty())
        No notes yet
    @endif

    <div class="mt-12 w-full space-y-4">
        @php
            $currentYear = null;
        @endphp

        @foreach ($notes as $note)
            @if ($note->published_at->year !== $currentYear)
                @php
                    $currentYear = $note->published_at->year;
                @endphp

                <h2 class="text-xl {{ $loop->first ? "" : "mt-8" }}">
                    {{ $currentYear }}
                </h2>
            @endif

            <x-note-index-item :note='$note' />
        @endforeach
    </div>

    <div class="my-8 flex justify-center">
        <div>
            @if (! $notes->onFirstPage())
                <button class="join-item btn">
                    <a href="{{ $notes->previousPageUrl() }}">Newer</a>
                </button>
            @endif

            @if ($notes->hasMorePages())
                <button class="join-item btn">
                    <a href="{{ $notes->nextPageUrl() }}">Older</a>
                </button>
            @endif
        </div>
    </div>
</x-layout.app>
