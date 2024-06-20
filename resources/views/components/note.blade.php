<x-card>
    <div class="prose">
        {!! $note->html() !!}
    </div>
    <div class="text-sm text-gray-600 flex justify-between">
        <div>
            {{ $note->created_at->format('Y F j \a\t g:ia') }}
        </div>
        <div>
            <a class='link' href='{{ route('notes.show', $note) }}'>View</a>
        </div>
    </div>
</x-card>
