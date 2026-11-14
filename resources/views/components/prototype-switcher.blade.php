{{--
    PROTOTYPE switcher: a floating pill that cycles ?variant= between a prototype's variants,
    with ← and → keys too. Only renders locally and on PR previews, never in production.
--}}
@props([
    'variants',
    'current',
])

@if (app()->isLocal() || env('IS_PULL_REQUEST'))
    @php
        $keys = array_keys($variants);
        $position = array_search($current, $keys);
        $previous = $keys[($position - 1 + count($keys)) % count($keys)];
        $next = $keys[($position + 1) % count($keys)];
        $previousUrl = request()->fullUrlWithQuery(['variant' => $previous]);
        $nextUrl = request()->fullUrlWithQuery(['variant' => $next]);
    @endphp

    <div
        class="bg-neutral text-neutral-content fixed bottom-4 left-1/2 z-50 flex -translate-x-1/2 items-center gap-1 rounded-full px-2 py-1 font-sans text-sm shadow-xl"
        x-data
        x-on:keydown.window="
            if ($event.target.closest('input, textarea, [contenteditable]')) return
            if ($event.key === 'ArrowLeft') window.location.assign(@js($previousUrl))
            if ($event.key === 'ArrowRight') window.location.assign(@js($nextUrl))
        "
    >
        <a
            class="btn btn-ghost btn-sm btn-circle text-neutral-content"
            href="{{ $previousUrl }}"
            aria-label="Previous variant"
        >←</a>
        <span class="px-2 whitespace-nowrap">{{ $current }} ({{ $variants[$current] }})</span>
        <a class="btn btn-ghost btn-sm btn-circle text-neutral-content" href="{{ $nextUrl }}" aria-label="Next variant"
            >→</a>
    </div>
@endif
