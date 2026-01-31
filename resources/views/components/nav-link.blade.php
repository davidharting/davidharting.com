@props([
    "href",
])

@php
    // Exact URL match is intentional - we want child pages (e.g. /notes/123)
    // to NOT highlight the parent nav item, making it clear users can click
    // the nav to return to the index page.
    $isActive = request()->url() === $href;
@endphp

<a
    href="{{ $href }}"
    @if ($isActive) aria-current="page" @endif
    class="btn btn-ghost btn-sm relative {{ $isActive ? "text-primary" : "" }}"
>
    {{ $slot }}
    @if ($isActive)
        <span
            class="absolute bottom-0 left-2 right-2 h-0.5 bg-primary rounded"
        ></span>
    @endif
</a>
