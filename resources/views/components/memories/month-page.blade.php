{{-- No name heading; the "other name | Yours" links say whose page this is. --}}
@php
    $month = $monthForViewer->month->calendarMonth;
    $revealed = $monthForViewer->month->hasRevealed();
    $otherName = $monthForViewer->other->displayName();
@endphp

<article class="mx-auto max-w-xl">
    <a class="link font-sans text-sm" href="{{ route("memories.index") }}">← All months</a>

    <x-type.page-title class="mt-8 text-5xl">{{ $month->label() }}</x-type.page-title>

    <nav class="mt-4 flex gap-6 text-xl">
        <a
            href="{{ route("memories.show", $month->toYearMonth()) }}"
            @if (! $showingYours) aria-current="page" @endif
            @class([
                'underline decoration-2 underline-offset-8' => ! $showingYours,
                'opacity-50 hover:opacity-100' => $showingYours,
            ])
        >{{ $otherName }}</a>
        <a
            href="{{ route("memories.show", ["month" => $month->toYearMonth(), "who" => "yours"]) }}"
            @if ($showingYours) aria-current="page" @endif
            @class([
                'underline decoration-2 underline-offset-8' => $showingYours,
                'opacity-50 hover:opacity-100' => ! $showingYours,
            ])
        >Yours</a>
    </nav>

    @if ($revealed)
        <p class="mt-4 font-sans text-sm opacity-60">{{ $monthForViewer->revealStatusLabel() }}</p>
    @endif

    <section class="mt-10">
        @if ($statusLabel() !== null)
            <p class="font-sans text-sm">
                <span class="opacity-60">{{ $statusLabel() }}</span>
                @if ($showingYours && $monthForViewer->viewerMemory?->isShared())
                    <span class="opacity-60">·</span>
                    <a class="link" href="{{ route("memories.write", $month->toYearMonth()) }}">Edit</a>
                @endif
            </p>
        @endif

        @if ($showingYours && $monthForViewer->viewerMemory?->isDraft())
            <div class="mt-4">
                <x-memories.cta :memory="$monthForViewer->viewerMemory" :month="$month" />
            </div>
        @endif

        @if ($shownMemory())
            <x-memories.body :memory="$shownMemory()" class="mt-5 text-lg leading-relaxed" />
        @elseif ($showingYours)
            <p class="mt-5 text-lg italic opacity-70">You haven't written about this month yet.</p>
        @elseif (! $revealed)
            <p class="mt-5 text-lg italic opacity-80">{{ $monthForViewer->revealStatusLabel() }}</p>
            @if (! $monthForViewer->viewerMemory?->isShared())
                <div class="mt-6">
                    <x-memories.cta :memory="$monthForViewer->viewerMemory" :month="$month" />
                </div>
            @endif
        @else
            <p class="mt-5 text-lg italic opacity-70">{{ $otherName }} didn't share a memory this month.</p>
        @endif

        @if ($showingYours && $monthForViewer->viewerMemory === null)
            <div class="mt-6">
                <x-memories.cta :memory="null" :month="$month" />
            </div>
        @endif
    </section>

    @if ($previous || $next)
        <nav class="mt-12 flex justify-between gap-4 font-sans text-sm">
            @if ($previous)
                <a class="link" href="{{ route("memories.show", $previous->toYearMonth()) }}"
                    >← {{ $previous->label() }}</a>
            @else
                <span></span>
            @endif

            @if ($next)
                <a class="link" href="{{ route("memories.show", $next->toYearMonth()) }}">{{ $next->label() }} →</a>
            @endif
        </nav>
    @endif
</article>
