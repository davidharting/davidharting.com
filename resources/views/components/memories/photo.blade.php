@props([
    'photo',
    'authorName',
])

@php
    $web = $photo->web();
@endphp

<figure {{ $attributes }}>
    @if ($web !== null)
        <div
            class="skeleton rounded-box w-full overflow-hidden"
            style="aspect-ratio: {{ $web->width }} / {{ $web->height }}"
        >
            <img
                src="{{ route("memories.photos.show", [$photo, "web"]) }}"
                width="{{ $web->width }}"
                height="{{ $web->height }}"
                alt="{{ $photo->caption ?? "A photo by {$authorName}" }}"
                loading="lazy"
                class="photo-fade h-full w-full object-cover text-transparent"
            />
        </div>
    @else
        <div class="rounded-box bg-base-200 p-4 font-sans text-sm opacity-70">
            {{ $photo->isFailed() ? "This photo couldn't be processed." : "This photo is still processing." }}
        </div>
    @endif

    @if ($photo->caption !== null)
        <figcaption class="mt-2 text-base italic opacity-70">{{ $photo->caption }}</figcaption>
    @endif
</figure>
