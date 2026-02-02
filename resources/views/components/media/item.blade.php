<div>
    <div class="text-sm text-base-content/60">{{ $getDate() }}</div>
    <div>
        <span>{{ $icon() }}</span>
        @can("viewAny", App\Models\Media::class)
            <a
                href="{{ route("media.show", $item->id) }}"
                class="link link-hover"
            >
                {{ $item->title }}
            </a>
        @else
            <span>{{ $item->title }}</span>
        @endcan
    </div>
    <div class="text-sm">
        {{ $item->creator }}
    </div>
    <x-media.note :item="$item" />
    @can("administrate")
        <a
            class="link link-neutral text-xs text-base-content/60"
            href="{{ route("filament.admin.resources.media.edit", $item->id) }}"
        >
            Edit
        </a>
    @endcan
</div>
