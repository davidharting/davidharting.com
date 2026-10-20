{{-- PROTOTYPE switcher: ← → cycle ?variant=, the menu picks ?state=. Never shown in production. --}}
@unless (app()->isProduction())
    @php
        $variants = \App\Http\Controllers\MemoryEditorPrototypeController::VARIANTS;
        $keys = array_keys($variants);
        $i = array_search($variant, $keys);
        $prev = $keys[($i - 1 + count($keys)) % count($keys)];
        $next = $keys[($i + 1) % count($keys)];
    @endphp
    <div class="fixed top-2 left-1/2 z-[100] -translate-x-1/2 font-sans">
        <div class="join bg-neutral text-neutral-content rounded-full shadow-xl">
            <a
                id="proto-prev"
                class="btn btn-neutral btn-sm join-item rounded-l-full"
                href="{{ request()->fullUrlWithQuery(['variant' => $prev]) }}"
            >←</a>
            <span class="join-item flex items-center px-2 text-xs whitespace-nowrap">{{ $variant }}</span>
            <select
                class="select select-sm join-item bg-neutral text-neutral-content w-36 border-0 text-xs"
                onchange="location.href = this.value"
            >
                @foreach (\App\Http\Controllers\MemoryEditorPrototypeController::STATES as $key => $label)
                    <option value="{{ request()->fullUrlWithQuery(['state' => $key]) }}" @selected($key === $state)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <a
                id="proto-next"
                class="btn btn-neutral btn-sm join-item rounded-r-full"
                href="{{ request()->fullUrlWithQuery(['variant' => $next]) }}"
            >→</a>
        </div>
    </div>
    <script>
        document.addEventListener('keydown', (e) => {
            if (e.target.closest('input, textarea, select, [contenteditable]')) return;
            if (e.key === 'ArrowLeft') document.getElementById('proto-prev').click();
            if (e.key === 'ArrowRight') document.getElementById('proto-next').click();
        });
    </script>
@endunless
