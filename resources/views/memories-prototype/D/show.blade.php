{{-- PROTOTYPE variant D: the month as one page of prose, the other person's memory first, yours below. --}}
<x-layout.app>
    @php $other = $m['other']->displayName(); @endphp
    <article class="mx-auto max-w-xl">
        @include('memories-prototype.D._nav')
        <h1 class="mt-8 text-5xl">{{ $m['month']->label() }}</h1>
        @if ($m['revealed'])
            <p class="mt-2 font-sans text-sm opacity-60">{{ $m['revealLine'] }}</p>
        @endif

        <div class="mt-10">
            @include('memories-prototype.D._memory', ['who' => $other, 'memory' => $m['theirs'], 'status' => $m['theirStatus'], 'photos' => $m['photos']['theirs'], 'late' => $m['theirsLate'], 'isMine' => false])
        </div>
        <div class="my-12 text-center tracking-[1em] opacity-40">···</div>
        @include('memories-prototype.D._memory', ['who' => 'You', 'memory' => $m['mine'], 'status' => $m['myStatus'], 'photos' => $m['photos']['mine'], 'late' => $m['mineLate'], 'isMine' => true])
    </article>
    <div class="h-24"></div>
    @include('memories-prototype._switcher')
</x-layout.app>
