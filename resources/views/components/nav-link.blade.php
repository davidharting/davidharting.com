@props([
    "href",
])

@php
    $isActive = request()->url() === $href;
@endphp

<a
    href="{{ $href }}"
    class="btn btn-ghost btn-sm relative {{ $isActive ? "text-primary" : "" }}"
>
    {{ $slot }}
    @if ($isActive)
        <span
            class="absolute bottom-0 left-2 right-2 h-0.5 bg-primary rounded"
        ></span>
    @endif
</a>
