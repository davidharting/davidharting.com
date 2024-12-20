<div>
    <div class="text-gray-600">{{ $getFinishedAt() }}</div>
    <div>
        <span>{{ $icon() }}</span>
        <span class="font-semibold">{{ $item->title }}</span>
    </div>
    <div class="text-sm">
        {{ $item->creator }}
    </div>
    <x-media.note :item="$item" />
</div>
