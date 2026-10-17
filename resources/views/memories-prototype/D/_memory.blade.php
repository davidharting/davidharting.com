{{-- One person's memory as a page of prose: name, a quiet status line, text, then photos inline. --}}
<section>
    <h2 class="text-3xl">{{ $who }}</h2>
    <p class="mt-1 font-sans text-sm opacity-60">
        {{ $status }}
        @if ($late)
            · shared after the reveal
        @endif
    </p>

    @if ($memory && ($isMine || ($m['revealed'] && $memory->isShared())))
        <div class="mt-5 text-lg leading-relaxed">@include('memories-prototype._body', ['memory' => $memory])</div>
        @for ($p = 0; $p < $photos; $p++)
            <figure class="mt-6">
                @include('memories-prototype._thumb', ['seed' => $memory->id + $p, 'label' => 'photo '.($p + 1).' · web copy', 'attributes' => new \Illuminate\View\ComponentAttributeBag(['class' => 'aspect-[4/3] w-full'])])
                <figcaption class="mt-2 text-sm italic opacity-70">Optional caption for photo {{ $p + 1 }}</figcaption>
            </figure>
        @endfor
        @include('memories-prototype._reactions', ['memory' => $memory])
    @elseif (! $isMine && ! $m['revealed'])
        <p class="mt-5 text-lg italic opacity-80">{{ $m['theirStatus'] }}. {{ $m['revealLine'] }}</p>
    @elseif (! $isMine)
        <p class="mt-5 text-lg italic opacity-70">{{ $who }} didn't share a memory this month.</p>
    @else
        <p class="mt-5 text-lg italic opacity-70">You haven't written about this month yet.</p>
    @endif

    @if ($isMine && $m['cta'])
        <a class="btn btn-primary mt-6" href="#">{{ $m['cta'] }}</a>
    @endif
</section>
