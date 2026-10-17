{{-- PROTOTYPE variant C: a grid of month tiles grouped by year, glanceable state, a peek at the other memory. --}}
<x-layout.app>
    <x-type.page-title>Memories</x-type.page-title>
    <p class="mt-2 font-sans text-sm opacity-70">Filled circle: shared. Dashed: a draft. Faint: not started.</p>

    @foreach ($months->groupBy(fn ($m) => $m['month']->year) as $year => $yearMonths)
        <h2 class="mt-10 text-2xl">{{ $year }}</h2>
        <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($yearMonths as $m)
                @php $other = $m['other']->displayName(); @endphp
                <a
                    href="{{ route('memories-prototype.show', ['month' => $m['slug'], 'variant' => $variant]) }}"
                    @class(['card hover:shadow-md transition-shadow', 'bg-base-200' => $m['revealed'], 'bg-accent/20' => ! $m['revealed']])
                >
                    <div class="card-body p-4">
                        <div class="flex items-start justify-between">
                            <h3 class="text-2xl">
                                {{ \Carbon\CarbonImmutable::create($m["month"]->year, $m["month"]->month)->format("F") }}
                            </h3>
                            @if ($m['photos']['mine'] + $m['photos']['theirs'] > 0 && $m['revealed'])
                                <span class="font-sans text-xs opacity-60">📷 {{ $m['photos']['mine'] + $m['photos']['theirs'] }}</span>
                            @endif
                        </div>
                        <div class="mt-1 flex gap-1">
                            @include('memories-prototype.C._dot', ['memory' => $m['mine'], 'initial' => substr($m['viewer']->displayName(), 0, 1), 'label' => 'You: '.$m['myStatus']])
                            @include('memories-prototype.C._dot', ['memory' => $m['revealed'] && $m['theirs']?->isDraft() ? null : $m['theirs'], 'initial' => substr($other, 0, 1), 'label' => $m['theirStatus']])
                        </div>
                        <p class="mt-2 line-clamp-3 text-sm">
                            @if ($m['revealed'] && $m['theirs']?->isShared())
                                “{{ \Illuminate\Support\Str::of($m['theirs']->body)->before("\n") }}”
                            @elseif ($m['revealed'] && ! $m['mine']?->isShared() && ! $m['theirs']?->isShared())
                                <span class="italic opacity-60">Nothing written</span>
                            @elseif (! $m['revealed'])
                                <span class="font-sans">{{ $m['theirStatus'] }}</span>
                            @else
                                <span class="italic opacity-60">{{ $m['theirStatus'] }}</span>
                            @endif
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    @endforeach

    <div class="h-24"></div>
    @include('memories-prototype._switcher')
</x-layout.app>
