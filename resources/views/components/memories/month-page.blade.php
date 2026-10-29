{{-- No name heading; the "other name | Yours" links say whose page this is. --}}
@php
    $month = $monthForViewer->month->calendarMonth;
    $revealed = $monthForViewer->month->hasRevealed();
    $otherName = $monthForViewer->other->displayName();
@endphp

<article class="mx-auto max-w-xl" data-memories-page="{{ $month->toYearMonth() }}">
    <nav class="flex flex-wrap justify-between gap-x-4 gap-y-2 font-sans text-sm">
        <a class="link tap-target" href="{{ route("memories.index") }}" wire:navigate.hover>← All months</a>

        <div class="flex gap-4 whitespace-nowrap">
            @if ($previous)
                <a
                    class="link tap-target"
                    href="{{ route("memories.show", $previous->toYearMonth()) }}"
                    aria-label="{{ $previous->label() }}"
                    wire:navigate.hover
                >← {{ $neighbourLabel($previous) }}</a>
            @endif

            @if ($next)
                <a
                    class="link tap-target"
                    href="{{ route("memories.show", $next->toYearMonth()) }}"
                    aria-label="{{ $next->label() }}"
                    wire:navigate.hover
                >{{ $neighbourLabel($next) }} →</a>
            @endif
        </div>
    </nav>

    <x-type.page-title class="mt-8 text-5xl">{{ $month->label() }}</x-type.page-title>

    <nav class="mt-4 flex gap-6 text-xl">
        <a
            href="{{ route("memories.show", $month->toYearMonth()) }}"
            @if (! $showingYours) aria-current="page" @endif
            wire:navigate.hover
            @class([
                'tap-target',
                'underline decoration-2 underline-offset-8' => ! $showingYours,
                'opacity-50 hover:opacity-100' => $showingYours,
            ])
        >{{ $otherName }}</a>
        <a
            href="{{ route("memories.show", ["month" => $month->toYearMonth(), "who" => "yours"]) }}"
            @if ($showingYours) aria-current="page" @endif
            wire:navigate.hover
            @class([
                'tap-target',
                'underline decoration-2 underline-offset-8' => $showingYours,
                'opacity-50 hover:opacity-100' => ! $showingYours,
            ])
        >Yours</a>
    </nav>

    <section class="mt-10">
        @if ($statusLabel() !== null)
            <p class="font-sans text-sm opacity-60">{{ $statusLabel() }}</p>
        @endif

        @if ($showingYours && $monthForViewer->viewerMemory?->isShared())
            <p class="font-sans text-sm">
                <a class="link tap-target" href="{{ route("memories.write", $month->toYearMonth()) }}">Edit</a>
            </p>
        @endif

        @if ($showingYours && $monthForViewer->viewerMemory?->isDraft())
            <div class="mt-4">
                <x-memories.cta :memory="$monthForViewer->viewerMemory" :month="$month" />
            </div>
        @endif

        @if ($shownMemory())
            <div id="memory-{{ $shownMemory()->id }}">
                @if ($revealed && ! $showingYours)
                    <x-memories.unveil :memory="$shownMemory()" />
                @endif

                <x-memories.body :memory="$shownMemory()" class="mt-5 text-lg leading-relaxed" />

                @if ($revealed)
                    <div class="mt-5">
                        <livewire:memories.reactions :memory="$shownMemory()" :key="'reactions-'.$shownMemory()->id" />
                    </div>
                @endif

                @foreach ($photos() as $photo)
                    <x-memories.photo
                        :photo="$photo"
                        :author-name="$showingYours ? $monthForViewer->viewer->displayName() : $otherName"
                        class="mt-8"
                    />
                @endforeach
            </div>
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
</article>
