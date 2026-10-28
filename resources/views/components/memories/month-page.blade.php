{{-- No name heading; the "other name | Yours" links say whose page this is. --}}
@php
    $month = $monthForViewer->month->calendarMonth;
    $revealed = $monthForViewer->month->hasRevealed();
    $otherName = $monthForViewer->other->displayName();
    $transition = \App\Memories\PrototypeTransition::for($month);
    $ym = $month->toYearMonth();
    $hero = $transition === 'photo' && ! $showingYours ? $photos()->first() : null;
@endphp

<article class="mx-auto max-w-xl" data-page-vt="{{ $transition }}" data-vt-month="{{ $ym }}" data-vt-keep>
    @if ($transition === 'expand')
        <div
            aria-hidden="true"
            @class(['pointer-events-none fixed inset-0 -z-10', 'bg-base-200' => $revealed, 'bg-accent/20' => ! $revealed])
            style="view-transition-name: card-{{ $ym }}; view-transition-class: card-expand"
        ></div>
    @endif

    <nav class="flex justify-between gap-4 font-sans text-sm">
        <a class="link -my-3 inline-block py-3" href="{{ route("memories.index") }}" wire:navigate.hover
            >← All months</a>

        <div class="flex gap-4">
            @if ($previous)
                <a
                    wire:navigate.hover
                    class="link -my-3 inline-block py-3"
                    href="{{ route("memories.show", $previous->toYearMonth()) }}"
                    aria-label="{{ $previous->label() }}"
                >← {{ $previous->name() }}</a>
            @endif

            @if ($next)
                <a
                    wire:navigate.hover
                    class="link -my-3 inline-block py-3"
                    href="{{ route("memories.show", $next->toYearMonth()) }}"
                    aria-label="{{ $next->label() }}"
                >{{ $next->name() }} →</a>
            @endif
        </div>
    </nav>

    @if ($hero !== null)
        <div
            class="rounded-box mt-6 aspect-video overflow-hidden bg-cover bg-center"
            style="view-transition-name: photo-{{ $ym }}; view-transition-class: photo-morph; background-image: url('{{ route("memories.photos.show", [$hero, "thumbnail"]) }}')"
        >
            <img
                src="{{ route("memories.photos.show", [$hero, "web"]) }}"
                alt="{{ $hero->caption ?? "A photo by {$otherName}" }}"
                class="h-full w-full object-cover"
            />
        </div>
    @endif

    <x-type.page-title
        class="mt-8 w-fit text-5xl"
        :style="\App\Memories\PrototypeTransition::fliesTitle($month) ? 'view-transition-name: month-'.$ym.'; view-transition-class: month-title' : null"
    >
        {{ $month->label() }}</x-type.page-title>

    <nav class="mt-4 flex gap-6 text-xl">
        <a
            wire:navigate.hover
            href="{{ route("memories.show", $month->toYearMonth()) }}"
            @if (! $showingYours) aria-current="page" @endif
            @class([
                'underline decoration-2 underline-offset-8' => ! $showingYours,
                'opacity-50 hover:opacity-100' => $showingYours,
                'press -my-2 py-2',
            ])
        >{{ $otherName }}</a>
        <a
            wire:navigate.hover
            href="{{ route("memories.show", ["month" => $month->toYearMonth(), "who" => "yours"]) }}"
            @if ($showingYours) aria-current="page" @endif
            @class([
                'underline decoration-2 underline-offset-8' => $showingYours,
                'opacity-50 hover:opacity-100' => ! $showingYours,
                'press -my-2 py-2',
            ])
        >Yours</a>
    </nav>

    <section class="mt-10">
        @if ($statusLabel() !== null)
            <p class="font-sans text-sm opacity-60">{{ $statusLabel() }}</p>
        @endif

        @if ($showingYours && $monthForViewer->viewerMemory?->isShared())
            <p class="font-sans text-sm">
                <a class="link" href="{{ route("memories.write", $month->toYearMonth()) }}">Edit</a>
            </p>
        @endif

        @if ($showingYours && $monthForViewer->viewerMemory?->isDraft())
            <div class="mt-4">
                <x-memories.cta :memory="$monthForViewer->viewerMemory" :month="$month" />
            </div>
        @endif

        @if ($shownMemory())
            <div id="shown-memory">
                @if ($revealed && ! $showingYours)
                    <script>
                        try {
                            const key = 'memory-unveiled-{{ $shownMemory()->id }}';
                            if (!localStorage.getItem(key)) {
                                document.getElementById('shown-memory').classList.add('animate-unveil');
                                localStorage.setItem(key, '1');
                            }
                        } catch (e) {}
                    </script>
                @endif
                <x-memories.body :memory="$shownMemory()" class="mt-5 text-lg leading-relaxed" />

                @if ($revealed)
                    <div class="mt-5">
                        <livewire:memories.reactions :memory="$shownMemory()" :key="'reactions-'.$shownMemory()->id" />
                    </div>
                @endif

                @foreach ($photos() as $photo)
                    @continue($hero !== null && $loop->first)
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
