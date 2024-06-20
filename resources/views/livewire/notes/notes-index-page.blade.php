<div>
    <x-crumb.container>
        <x-crumb.item :url="route('notes.index')">Notes</x-crumb.item>
    </x-crumb.container>

    <x-type.page-title>Notes</x-type.page-title>

    <div class="mt-12 w-full space-y-4">
        @foreach ($notes as $note)
            <x-note :note='$note' />
        @endforeach
    </div>

    <div class="my-8 flex justify-center">
        <div>
            @if (!$notes->onFirstPage())
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
