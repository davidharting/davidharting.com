@php
    $month = $monthForViewer->month->calendarMonth;
    $revealed = $monthForViewer->month->hasRevealed();
    $leadingPhotos = $thumbnails();
    $monthUrl = route('memories.show', [
        'month' => $month->toYearMonth(),
        'v' => $pageVersion,
    ]);
@endphp

@if ($monthForViewer->month->missed())
    <div class="flex items-center justify-between gap-4 px-4 py-1 opacity-70">
        <h2 class="text-lg">
            <a
                href="{{ $monthUrl }}"
                wire:navigate.hover
                data-prefetch
                class="inline-block py-2"
            >{{ $month->label() }}</a>
        </h2>
        <x-memories.cta :memory="$monthForViewer->viewerMemory" :month="$month" quiet class="-mr-3" />
    </div>
@else
    <div @class([
        'card relative transition hover:shadow-md active:scale-[0.98] active:brightness-95 duration-150',
        'bg-base-200' => $revealed,
        'bg-accent/20' => ! $revealed,
    ])>
        <div class="card-body gap-3 p-4">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-2xl">
                    <a
                        href="{{ $monthUrl }}"
                        wire:navigate.hover
                        data-prefetch
                        style="view-transition-name: month-{{ $month->toYearMonth() }}; view-transition-class: month-title"
                        onclick="this.closest('.card').classList.add('is-pressed')"
                        class="after:rounded-box focus-visible:after:outline-primary after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:outline-2"
                    >{{ $month->label() }}</a>
                </h2>
                <div class="flex gap-1">
                    <x-memories.initial
                        :memory="$monthForViewer->otherMemory"
                        :name="$monthForViewer->other->displayName()"
                    />
                    <x-memories.initial
                        :memory="$monthForViewer->viewerMemory"
                        :name="$monthForViewer->viewer->displayName()"
                    />
                </div>
            </div>

            @if ($leadingPhotos->isNotEmpty())
                <div @class(['grid gap-2', 'grid-cols-2' => $leadingPhotos->count() > 1])>
                    @foreach ($leadingPhotos as $photo)
                        @php
                            $thumbnail = $photo->thumbnail();
                        @endphp

                        <div @class([
                            'skeleton rounded-box overflow-hidden',
                            'aspect-square' => $leadingPhotos->count() > 1,
                            'aspect-video' => $leadingPhotos->count() === 1,
                        ])>
                            <img
                                src="{{ route("memories.photos.show", [$photo, "thumbnail"]) }}"
                                width="{{ $thumbnail->width }}"
                                height="{{ $thumbnail->height }}"
                                alt="{{ $photo->caption ?? "A photo by {$authorNameOf($photo)}" }}"
                                loading="lazy"
                                class="photo-fade h-full w-full object-cover text-transparent"
                            />
                        </div>
                    @endforeach
                </div>
            @endif

            @if (! $revealed)
                <p class="font-sans text-sm">
                    {{ $monthForViewer->otherStatusLabel() }} · Yours: {{ $monthForViewer->viewerStatusLabel() }}
                </p>
            @elseif ($snippet() !== null)
                <p class="line-clamp-2 text-sm opacity-80">{{ $snippet() }}</p>
            @endif

            @if ($monthForViewer->viewerMissed())
                <x-memories.cta
                    :memory="$monthForViewer->viewerMemory"
                    :month="$month"
                    quiet
                    class="relative -ml-3 self-start opacity-80"
                />
            @endif
        </div>
    </div>
@endif
