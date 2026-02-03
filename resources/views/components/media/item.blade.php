<div>
    <div class="text-sm text-base-content/60">{{ $getDate() }}</div>
    <div>
        <span>{{ $icon() }}</span>
        @if ($canViewMedia)
            <a
                href="{{ route("media.show", $item->id) }}"
                class="link link-hover"
            >
                {{ $item->title }}
            </a>
        @else
            <span>{{ $item->title }}</span>
        @endif
    </div>
    <div class="text-sm">
        {{ $item->creator }}
    </div>
    <x-media.note :item="$item" :can-see-note="$canSeeNote" />
    @if ($canAdministrate)
        <a
            class="link link-neutral text-xs text-base-content/60"
            href="{{ route("filament.admin.resources.media.edit", $item->id) }}"
        >
            Edit
        </a>
    @endif
</div>
