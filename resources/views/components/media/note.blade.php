@can("seeNote", App\Models\Media::class)
    @if ($item->note)
        <div class="text-xs text-gray-600">
            {{ trim($item->note) }}
        </div>
    @endif

    @if ($item->finished_comment)
        <div class="text-xs text-gray-600">
            {{ trim($item->finished_comment) }}
        </div>
    @endif
@endcan
