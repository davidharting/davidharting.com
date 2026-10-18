{{-- One person's memory per page, switched with plain text links. No name heading; the links say whose page this is. --}}
<x-layout.app>
    @php
        $month = $memoryMonth->month;
        $other = $memoryMonth->other->displayName();
        $mine = $memoryMonth->mine;
        $theirs = $memoryMonth->theirs;
        $revealed = $memoryMonth->isRevealed();
        $memory = $showingYours ? $mine : $memoryMonth->readableTheirs();
    @endphp

    <article class="mx-auto max-w-xl">
        <div class="flex items-center justify-between font-sans text-sm">
            <a class="link" href="{{ route("memories.index") }}">← All months</a>
            <span class="flex gap-4">
                @if ($older)
                    <a class="link" href="{{ route("memories.show", $older->month->toYearMonth()) }}">Older</a>
                @endif

                @if ($newer)
                    <a class="link" href="{{ route("memories.show", $newer->month->toYearMonth()) }}">Newer</a>
                @endif
            </span>
        </div>

        <x-type.page-title class="mt-8 text-5xl">{{ $month->label() }}</x-type.page-title>

        <nav class="mt-4 flex gap-6 text-xl">
            @foreach ([['label' => $other, 'yours' => false, 'query' => []], ['label' => 'Yours', 'yours' => true, 'query' => ['who' => 'yours']]] as $link)
                @php
                    $current = $link['yours'] === $showingYours;
                @endphp

                <a
                    href="{{ route("memories.show", ["month" => $month->toYearMonth(), ...$link["query"]]) }}"
                    @if ($current) aria-current="page" @endif
                    @class([
                        'underline decoration-2 underline-offset-8' => $current,
                        'opacity-50 hover:opacity-100' => ! $current,
                    ])
                >{{ $link["label"] }}</a>
            @endforeach
        </nav>

        @if ($revealed)
            <p class="mt-4 font-sans text-sm opacity-60"><x-memories.reveal-line :memory-month="$memoryMonth" /></p>
        @endif

        <section class="mt-10">
            <p class="font-sans text-sm opacity-60">
                @if ($showingYours)
                    @if ($mine)
                        <x-memories.my-status :memory="$mine" />
                    @endif
                @elseif (! $revealed || $theirs?->isShared())
                    <x-memories.their-status :memory-month="$memoryMonth" />
                @endif

                @if ($memoryMonth->sharedLate($showingYours ? $mine : $theirs))
                    · shared after the reveal
                @endif
            </p>

            @if ($memory)
                <x-memories.body :memory="$memory" class="mt-5 text-lg leading-relaxed" />
            @elseif ($showingYours)
                <p class="mt-5 text-lg italic opacity-70">You haven't written about this month yet.</p>
            @elseif (! $revealed)
                <p class="mt-5 text-lg italic opacity-80"><x-memories.reveal-line :memory-month="$memoryMonth" /></p>
            @else
                <p class="mt-5 text-lg italic opacity-70">{{ $other }} didn't share a memory this month.</p>
            @endif

            @if ($showingYours)
                <div class="mt-6">
                    <x-memories.cta :memory="$mine" />
                </div>
            @endif
        </section>
    </article>
</x-layout.app>
