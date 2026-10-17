{{-- PROTOTYPE variant E: one person's memory per page, switched with plain text links instead of tabs. --}}
<x-layout.app>
    @php
        $other = $m['other']->displayName();
        $who = request('who', 'theirs') === 'mine' ? 'mine' : 'theirs';
    @endphp
    <article class="mx-auto max-w-xl">
        @include('memories-prototype.D._nav')
        <h1 class="mt-8 text-5xl">{{ $m['month']->label() }}</h1>
        <nav class="mt-4 flex gap-6 text-xl">
            @foreach (['theirs' => $other, 'mine' => 'Yours'] as $key => $label)
                <a
                    href="{{ request()->fullUrlWithQuery(['who' => $key]) }}"
                    @class(['underline underline-offset-8 decoration-2' => $who === $key, 'opacity-50 hover:opacity-100' => $who !== $key])
                >{{ $label }}</a>
            @endforeach
        </nav>
        @if ($m['revealed'])
            <p class="mt-4 font-sans text-sm opacity-60">{{ $m['revealLine'] }}</p>
        @endif

        <div class="mt-10">
            @if ($who === 'theirs')
                @include('memories-prototype.D._memory', ['who' => $other, 'memory' => $m['theirs'], 'status' => $m['theirStatus'], 'photos' => $m['photos']['theirs'], 'late' => $m['theirsLate'], 'isMine' => false])
            @else
                @include('memories-prototype.D._memory', ['who' => 'You', 'memory' => $m['mine'], 'status' => $m['myStatus'], 'photos' => $m['photos']['mine'], 'late' => $m['mineLate'], 'isMine' => true])
            @endif
        </div>
    </article>
    <div class="h-24"></div>
    @include('memories-prototype._switcher')
</x-layout.app>
