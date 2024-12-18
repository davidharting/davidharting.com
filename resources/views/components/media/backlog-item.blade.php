<div>
    <div class="text-gray-600">{{ $getAddedAt() }}</div>
    <div>
        <span>{{ $icon() }}</span>
        <span class="font-semibold">{{ $item->title }}</span>
    </div>
    <div class="text-sm">
        {{ $item->creator }}
    </div>
</div>
