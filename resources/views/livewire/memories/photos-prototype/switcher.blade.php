{{-- PROTOTYPE (#299): floating variant switcher. Never shown in production, or with ?bar=0. --}}
@php
    $variants = ['A' => 'Grid, caption lines', 'B' => 'Inline list', 'C' => 'Filmstrip + arrange', 'D' => 'Grid, caption chips'];
    $keys = array_keys($variants);
    $at = array_search($this->prototypeVariant, $keys);
    $link = fn (string $key) => url()->current().'?'.http_build_query(['variant' => $key, 'photos' => $this->prototypePhotos]);
    $previous = $link($keys[($at + 3) % 4]);
    $next = $link($keys[($at + 1) % 4]);
@endphp

@if (! app()->isProduction() && request('bar') !== '0')
    <div
        wire:ignore
        x-data
        x-on:keydown.window="
            if ($event.target.closest('input, textarea, [contenteditable]')) return;
            if ($event.key === 'ArrowLeft') window.location = @js($previous);
            if ($event.key === 'ArrowRight') window.location = @js($next);
        "
        class="fixed bottom-2 left-1/2 z-50 -translate-x-1/2 rounded-full bg-black px-2 py-1 font-sans text-xs text-white shadow-lg"
    >
        <a href="{{ $previous }}" class="px-2">←</a>
        <span>{{ $this->prototypeVariant }} ({{ $variants[$this->prototypeVariant] }})</span>
        <a href="{{ $next }}" class="px-2">→</a>
    </div>
@endif
