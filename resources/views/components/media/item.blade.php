<div>
    <div class="text-sm text-gray-600">{{ $getDate() }}</div>
    <div>
        <span>{{ $icon() }}</span>
        <span class="">{{ $item->title }}</span>
    </div>
    <div class="text-sm">
        {{ $item->creator }}
    </div>
    <x-media.note :item="$item" />
</div>
