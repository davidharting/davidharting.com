{{-- PROTOTYPE variant C: one memory at a time behind tabs; before the reveal the other tab is a waiting card. --}}
<x-layout.app>
    @php $other = $m['other']->displayName(); @endphp
    <div class="mx-auto max-w-2xl">
        <div class="flex items-center justify-between font-sans text-sm">
            @if ($older)
                <a
                    class="btn btn-ghost btn-sm"
                    href="{{ route('memories-prototype.show', ['month' => $older['slug'], 'variant' => $variant]) }}"
                >‹</a>
            @else
                <span></span>
            @endif
            <a class="link" href="{{ route('memories-prototype.index', ['variant' => $variant]) }}">All months</a>
            @if ($newer)
                <a
                    class="btn btn-ghost btn-sm"
                    href="{{ route('memories-prototype.show', ['month' => $newer['slug'], 'variant' => $variant]) }}"
                >›</a>
            @else
                <span></span>
            @endif
        </div>

        <h1 class="mt-4 text-center text-5xl">{{ $m['month']->label() }}</h1>
        <p class="mt-2 text-center font-sans text-sm opacity-70">{{ $m['revealLine'] }}</p>

        <div class="tabs tabs-lift mt-8">
            @foreach ([[$other, $m['theirs'], $m['theirStatus'], $m['photos']['theirs'], $m['theirsLate'], false], ['You', $m['mine'], $m['myStatus'], $m['photos']['mine'], $m['mineLate'], true]] as [$who, $memory, $status, $photos, $late, $isMine])
                <input
                    type="radio"
                    name="who"
                    class="tab text-lg"
                    aria-label="{{ $who }}"
                    @checked($m['revealed'] ? ! $isMine : $isMine)
                />
                <div class="tab-content bg-base-100 border-base-300 p-6">
                    <p class="font-sans text-sm opacity-70">
                        {{ $status }}
                        @if ($late)
                            <span class="badge badge-accent badge-sm">shared after the reveal</span>
                        @endif
                    </p>
                    @if ($memory && ($isMine || ($m['revealed'] && $memory->isShared())))
                        @include('memories-prototype._photos', ['count' => $photos, 'layout' => 'strip'])
                        <div class="mt-4 text-lg leading-relaxed">
                            @include('memories-prototype._body', ['memory' => $memory])
                        </div>
                        @include('memories-prototype._reactions', ['memory' => $memory])
                    @elseif (! $isMine && ! $m['revealed'])
                        <div class="hero bg-base-200 rounded-box mt-4 py-12">
                            <div class="hero-content text-center">
                                <div>
                                    <p class="text-2xl">{{ $m['theirStatus'] }}.</p>
                                    <p class="mt-2 font-sans text-sm opacity-80">{{ $m['revealLine'] }}</p>
                                </div>
                            </div>
                        </div>
                    @elseif (! $isMine)
                        <p class="mt-4 italic opacity-70">{{ $other }} didn't share a memory this month.</p>
                    @else
                        <p class="mt-4 italic opacity-70">You haven't written about this month yet.</p>
                    @endif
                    @if ($isMine && $m['cta'])
                        <a class="btn btn-primary mt-6" href="#">{{ $m['cta'] }}</a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    <div class="h-24"></div>
    @include('memories-prototype._switcher')
</x-layout.app>
