<div>
    <div>
        <span>{{ $icon() }}</span>
        <span class="font-semibold">{{ $item->title }}</span>
    </div>
    @if ($item->creator)
        <div class="text-sm">
            {{ $item->creator }}
        </div>
    @endif
</div>
