<x-layout.app title="David's Notes" description="Notes from David">
    <div>
        <x-type.page-title>Notes</x-type.page-title>
        @if ($notes->isEmpty())
            No notes yet
        @endif

        <div class="mt-12 w-full space-y-6">
            @foreach ($notes as $note)
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
    </div>
</x-layout.app>
