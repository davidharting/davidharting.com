<div>
    <x-media.item :item="$item" />
    <div class="text-gray-600 text-sm">Added on {{ $getAddedAt() }}</div>
    <x-media.note :item="$item" />
</div>
