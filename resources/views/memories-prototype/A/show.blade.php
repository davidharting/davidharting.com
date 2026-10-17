{{-- PROTOTYPE variant A: both memories side by side, the reveal state as a banner. --}}
<x-layout.app>
    @php $other = $m['other']->displayName(); @endphp
    <div class="flex items-center justify-between font-sans text-sm">
        <a class="link" href="{{ route('memories-prototype.index', ['variant' => $variant]) }}">← All months</a>
        <div class="join">
            @if ($older)
                <a
                    class="btn btn-sm join-item"
                    href="{{ route('memories-prototype.show', ['month' => $older['slug'], 'variant' => $variant]) }}"
                >{{ $older['month']->label() }}</a>
            @endif
            @if ($newer)
                <a
                    class="btn btn-sm join-item"
                    href="{{ route('memories-prototype.show', ['month' => $newer['slug'], 'variant' => $variant]) }}"
                >{{ $newer['month']->label() }}</a>
            @endif
        </div>
    </div>

    <x-type.page-title class="mt-6">{{ $m['month']->label() }}</x-type.page-title>

    <div role="alert" @class(['alert mt-6 font-sans', 'alert-info' => ! $m['revealed']])>
        <span>{{ $m['revealLine'] }}</span>
    </div>

    <div class="mt-8 grid items-start gap-6 md:grid-cols-2">
        @foreach ([[$other, $m['theirs'], $m['theirStatus'], $m['photos']['theirs'], $m['theirsLate'], false], ['You', $m['mine'], $m['myStatus'], $m['photos']['mine'], $m['mineLate'], true]] as [$who, $memory, $status, $photos, $late, $isMine])
            <div class="card bg-base-200">
                <div class="card-body">
                    <h2 class="card-title text-2xl">{{ $who }}</h2>
                    <p class="font-sans text-sm opacity-70">
                        {{ $status }}
                        @if ($late)
                            <span class="badge badge-sm badge-accent">shared after the reveal</span>
                        @endif
                    </p>

                    @if ($memory && ($isMine || ($m['revealed'] && $memory->isShared())))
                        <div class="mt-2">@include('memories-prototype._body', ['memory' => $memory])</div>
                        @include('memories-prototype._photos', ['count' => $photos, 'layout' => 'grid'])
                        @include('memories-prototype._reactions', ['memory' => $memory])
                    @elseif (! $isMine && ! $m['revealed'])
                        <div class="bg-base-300 rounded-box mt-2 flex h-40 items-center justify-center italic opacity-70">
                            Hidden until the reveal
                        </div>
                    @elseif (! $isMine)
                        <p class="mt-2 italic opacity-70">Nothing to read this month.</p>
                    @endif

                    @if ($isMine && $m['cta'])
                        <div class="card-actions mt-4"><a class="btn btn-primary" href="#">{{ $m['cta'] }}</a></div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="h-24"></div>
    @include('memories-prototype._switcher')
</x-layout.app>
