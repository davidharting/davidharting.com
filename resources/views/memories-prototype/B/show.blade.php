{{-- PROTOTYPE variant B: one month as a single column of letters, everything in full. --}}
<x-layout.app>
    <div class="mx-auto max-w-3xl">
        <a class="link font-sans text-sm" href="{{ route('memories-prototype.index', ['variant' => $variant]) }}"
            >← All memories</a>
        <div class="mt-6">@include('memories-prototype.B._month', ['m' => $m, 'full' => true])</div>
        <div class="mt-12 flex justify-between font-sans text-sm">
            <span>
                @if ($older)
                    <a
                        class="link"
                        href="{{ route('memories-prototype.show', ['month' => $older['slug'], 'variant' => $variant]) }}"
                    >← {{ $older['month']->label() }}</a>
                @endif
            </span>
            <span>
                @if ($newer)
                    <a
                        class="link"
                        href="{{ route('memories-prototype.show', ['month' => $newer['slug'], 'variant' => $variant]) }}"
                    >{{ $newer['month']->label() }} →</a>
                @endif
            </span>
        </div>
    </div>
    <div class="h-24"></div>
    @include('memories-prototype._switcher')
</x-layout.app>
