{{-- PROTOTYPE placeholders for photos (none are seeded yet). $layout: grid | stack | strip --}}
@if ($count > 0)
    @if ($layout === 'stack')
        <div class="mt-4 space-y-4">
            @for ($p = 1; $p <= $count; $p++)
                <figure>
                    <div class="bg-base-300 rounded-box flex aspect-[4/3] w-full items-center justify-center font-sans text-sm opacity-70">
                        photo {{ $p }} (2048 px web copy)
                    </div>
                    <figcaption class="mt-1 text-sm italic opacity-70">Optional caption for photo {{ $p }}</figcaption>
                </figure>
            @endfor
        </div>
    @elseif ($layout === 'strip')
        <div class="carousel carousel-center rounded-box mt-4 w-full gap-2">
            @for ($p = 1; $p <= $count; $p++)
                <div class="carousel-item">
                    <div class="bg-base-300 rounded-box flex h-48 w-64 items-center justify-center font-sans text-sm opacity-70">
                        photo {{ $p }}
                    </div>
                </div>
            @endfor
        </div>
    @else
        <div class="mt-4 grid grid-cols-3 gap-2">
            @for ($p = 1; $p <= $count; $p++)
                <div class="bg-base-300 rounded-box flex aspect-square items-center justify-center font-sans text-xs opacity-70">
                    512 px
                </div>
            @endfor
        </div>
    @endif
@endif
