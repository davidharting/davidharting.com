{{-- No name heading; the Katie | Yours links say whose page this is. --}}
<article class="mx-auto max-w-xl">
    <div class="flex items-center justify-between font-sans text-sm">
        <a class="link" href="{{ route("memories.index") }}">← All months</a>
        <span class="flex gap-4">
            @if ($older)
                <a class="link" href="{{ route("memories.show", $older->toYearMonth()) }}">Older</a>
            @endif

            @if ($newer)
                <a class="link" href="{{ route("memories.show", $newer->toYearMonth()) }}">Newer</a>
            @endif
        </span>
    </div>

    <x-type.page-title class="mt-8 text-5xl">{{ $month->label() }}</x-type.page-title>

    <nav class="mt-4 flex gap-6 text-xl">
        <a
            href="{{ route("memories.show", $month->toYearMonth()) }}"
            @if (! $showingYours) aria-current="page" @endif
            @class([
                'underline decoration-2 underline-offset-8' => ! $showingYours,
                'opacity-50 hover:opacity-100' => $showingYours,
            ])
        >{{ $other->displayName() }}</a>
        <a
            href="{{ route("memories.show", ["month" => $month->toYearMonth(), "who" => "yours"]) }}"
            @if ($showingYours) aria-current="page" @endif
            @class([
                'underline decoration-2 underline-offset-8' => $showingYours,
                'opacity-50 hover:opacity-100' => ! $showingYours,
            ])
        >Yours</a>
    </nav>

    @if ($reveal->hasHappened())
        <p class="mt-4 font-sans text-sm opacity-60">{{ $revealStatusLabel() }}</p>
    @endif

    <section class="mt-10">
        @if ($statusLabel() !== null)
            <p class="font-sans text-sm opacity-60">{{ $statusLabel() }}</p>
        @endif

        @if ($shownMemory())
            <x-memories.body :memory="$shownMemory()" class="mt-5 text-lg leading-relaxed" />
        @elseif ($showingYours)
            <p class="mt-5 text-lg italic opacity-70">You haven't written about this month yet.</p>
        @elseif (! $reveal->hasHappened())
            <p class="mt-5 text-lg italic opacity-80">{{ $revealStatusLabel() }}</p>
        @else
            <p class="mt-5 text-lg italic opacity-70">{{ $other->displayName() }} didn't share a memory this month.</p>
        @endif

        @if ($showingYours)
            <div class="mt-6">
                <x-memories.cta :memory="$viewerMemory" />
            </div>
        @endif
    </section>
</article>
