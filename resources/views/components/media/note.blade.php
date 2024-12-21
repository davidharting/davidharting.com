@can("seeNote", App\Models\Media::class)
    @if ($item->note)
        <div class="text-xs text-gray-600">
            {{ $item->note }}
        </div>
    @endif
@endcan
