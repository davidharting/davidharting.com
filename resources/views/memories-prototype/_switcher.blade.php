{{-- PROTOTYPE switcher: cycles ?variant= with the arrows or the ← → keys. Never shown in production. --}}
@unless (app()->isProduction())
    @php
        $keys = array_keys(\App\Http\Controllers\MemoriesPrototypeController::VARIANTS);
        $i = array_search($variant, $keys);
        $prev = $keys[($i - 1 + count($keys)) % count($keys)];
        $next = $keys[($i + 1) % count($keys)];
    @endphp
    <div class="fixed bottom-4 left-1/2 z-50 -translate-x-1/2">
        <div class="join bg-neutral text-neutral-content rounded-full font-sans shadow-xl">
            <a
                id="proto-prev"
                class="btn btn-neutral join-item rounded-l-full"
                href="{{ request()->fullUrlWithQuery(['variant' => $prev]) }}"
            >←</a>
            <span class="join-item flex items-center px-3 text-xs whitespace-nowrap sm:text-sm">
                {{ $variant }} ({{ \App\Http\Controllers\MemoriesPrototypeController::VARIANTS[$variant] }})
            </span>
            <a
                id="proto-next"
                class="btn btn-neutral join-item rounded-r-full"
                href="{{ request()->fullUrlWithQuery(['variant' => $next]) }}"
            >→</a>
        </div>
    </div>
    <script>
        document.addEventListener('keydown', (e) => {
            if (e.target.closest('input, textarea, [contenteditable]')) return;
            if (e.key === 'ArrowLeft') document.getElementById('proto-prev').click();
            if (e.key === 'ArrowRight') document.getElementById('proto-next').click();
        });
    </script>
@endunless
