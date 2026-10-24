@props([
    'photo',
])

<figure {{ $attributes }}>
    @if ($photo->isReady())
        <img
            src="{{ route("memories.photos.show", [$photo, "web"]) }}"
            width="{{ $photo->web()->width }}"
            height="{{ $photo->web()->height }}"
            alt="{{ $photo->caption ?? "Photo" }}"
            loading="lazy"
            class="rounded-box bg-base-300 h-auto w-full"
        />
    @else
        <div class="rounded-box bg-base-200 p-4 font-sans text-sm opacity-70">
            {{ $photo->isFailed() ? "This photo couldn't be processed." : "This photo is still processing." }}
        </div>
    @endif

    @if ($photo->caption !== null)
        <figcaption class="mt-2 text-base italic opacity-70">{{ $photo->caption }}</figcaption>
    @endif
</figure>
