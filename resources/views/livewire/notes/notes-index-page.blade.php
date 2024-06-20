<div>
    <x-crumb.container>
        <x-crumb.item :url="route('notes.index')">Notes</x-crumb.item>
    </x-crumb.container>

    <x-type.page-title>Notes</x-type.page-title>

    <div class="mt-12 w-full space-y-4">
        @foreach ($notes as $note)
            <x-card>
                <div class='prose'>
                    {!! $note->html() !!}
                </div>
                <div class="text-sm text-gray-600">
                    {{ $note->created_at->format('Y F j \a\t g:ia') }}
                </div>
            </x-card>
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
