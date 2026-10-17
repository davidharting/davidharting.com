{{-- PROTOTYPE variant D: one column of month tiles, photos first, a line of text under them. --}}
<x-layout.app>
    <div class="mx-auto max-w-xl">
        <h1 class="text-5xl">Memories</h1>

        <div class="mt-8 space-y-4">
            @foreach ($months as $m)
                @php
                    $other = $m['other']->displayName();
                    $readable = collect([[$m['theirs'], $m['photos']['theirs']], [$m['mine'], $m['photos']['mine']]])
                        ->filter(fn ($pair) => $pair[0]?->isShared() && $m['revealed']);
                    $thumbs = $readable->flatMap(fn ($pair) => $pair[1] > 0 ? array_fill(0, $pair[1], $pair[0]->id) : [])->take(2);
                    $snippet = $readable->first()[0] ?? null;
                    $empty = ! $m['mine'] && ! $m['theirs'];
                @endphp
                <a
                    href="{{ route('memories-prototype.show', ['month' => $m['slug'], 'variant' => $variant]) }}"
                    @class(['card block transition-shadow hover:shadow-md', 'bg-base-200' => $m['revealed'], 'bg-accent/20' => ! $m['revealed']])
                >
                    <div class="card-body gap-3 p-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-2xl">{{ $m['month']->label() }}</h2>
                            <div class="flex gap-1">
                                @include('memories-prototype.C._dot', ['memory' => $m['revealed'] && $m['theirs']?->isDraft() ? null : $m['theirs'], 'initial' => substr($other, 0, 1), 'label' => $m['theirStatus']])
                                @include('memories-prototype.C._dot', ['memory' => $m['mine'], 'initial' => substr($m['viewer']->displayName(), 0, 1), 'label' => 'You: '.$m['myStatus']])
                            </div>
                        </div>

                        @if ($thumbs->isNotEmpty())
                            <div @class(['grid gap-2', 'grid-cols-2' => $thumbs->count() > 1])>
                                @foreach ($thumbs as $i => $seed)
                                    @include('memories-prototype._thumb', ['seed' => $seed + $i, 'attributes' => new \Illuminate\View\ComponentAttributeBag(['class' => $thumbs->count() > 1 ? 'aspect-square' : 'aspect-[16/9]'])])
                                @endforeach
                            </div>
                        @endif

                        @if (! $m['revealed'])
                            <p class="font-sans text-sm">
                                {{ $m['theirStatus'] }}. {{ $m['cta'] ? 'Yours: '.strtolower($m['myStatus']).'.' : 'Yours is shared.' }}
                            </p>
                        @elseif ($snippet)
                            <p class="line-clamp-2 text-sm opacity-80">
                                {{ \Illuminate\Support\Str::of($snippet->body)->before("\n") }}
                            </p>
                        @elseif ($empty)
                            <p class="text-sm italic opacity-60">
                                Nothing written. <span class="link">Write yours</span>
                            </p>
                        @else
                            <p class="text-sm italic opacity-60">{{ $m['theirStatus'] }}</p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    <div class="h-24"></div>
    @include('memories-prototype._switcher')
</x-layout.app>
