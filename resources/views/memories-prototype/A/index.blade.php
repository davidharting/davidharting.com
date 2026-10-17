{{-- PROTOTYPE variant A: a compact index of months, one row each, states as badges. --}}
<x-layout.app>
    <x-type.page-title>Memories</x-type.page-title>

    <ul class="list bg-base-200 rounded-box mt-8">
        @foreach ($months as $m)
            @php $other = $m['other']->displayName(); @endphp
            <li class="list-row items-center">
                <div class="w-36">
                    <a
                        class="link link-hover text-xl"
                        href="{{ route('memories-prototype.show', ['month' => $m['slug'], 'variant' => $variant]) }}"
                    >
                        {{ $m['month']->label() }}
                    </a>
                </div>
                <div class="list-col-grow flex flex-wrap gap-2 font-sans text-sm">
                    <span @class(['badge', 'badge-success' => $m['mine']?->isShared(), 'badge-warning' => $m['mine']?->isDraft(), 'badge-ghost' => ! $m['mine']])>
                        You · {{ $m['mine'] ? ($m['mine']->isShared() ? 'shared' : 'draft') : 'not started' }}
                    </span>
                    <span @class(['badge', 'badge-success' => $m['theirs']?->isShared(), 'badge-warning' => $m['theirs']?->isDraft(), 'badge-ghost' => ! $m['theirs']])>
                        {{ $other }} · {{ $m['theirs'] ? ($m['theirs']->isShared() ? 'shared' : ($m['revealed'] ? 'not shared' : 'started')) : 'not started' }}
                    </span>
                    @if ($m['revealed'])
                        <span class="badge badge-outline">Revealed</span>
                    @else
                        <span class="badge badge-outline badge-accent">Not yet revealed</span>
                    @endif
                </div>
                @if ($m['cta'])
                    <a class="btn btn-primary btn-sm" href="#">{{ $m['cta'] }}</a>
                @endif
            </li>
        @endforeach
    </ul>

    <div class="h-24"></div>
    @include('memories-prototype._switcher')
</x-layout.app>
