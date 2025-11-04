@can("seeNote", App\Models\Media::class)
    @if ($item->note)
        <div class="text-xs text-gray-600">
            {{ trim($item->note) }}
        </div>
    @endif

    @if (isset($item->finished_comment) && $item->finished_comment)
        <div class="text-xs text-gray-600">
            {{ trim($item->finished_comment) }}
        </div>
    @endif
@endcan
