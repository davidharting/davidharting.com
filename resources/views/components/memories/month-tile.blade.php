@php
    $month = $monthForViewer->month->calendarMonth;
    $revealed = $monthForViewer->month->hasRevealed();
    $leadingPhotos = $thumbnails();
@endphp

<a
    href="{{ route("memories.show", $month->toYearMonth()) }}"
    @class([
        'card block transition-shadow hover:shadow-md',
        'bg-base-200' => $revealed,
        'bg-accent/20' => ! $revealed,
    ])
>
    <div class="card-body gap-3 p-4">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-2xl">{{ $month->label() }}</h2>
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
                    <img
                        src="{{ route("memories.photos.show", [$photo, "thumbnail"]) }}"
                        width="{{ $photo->thumbnail()->width }}"
                        height="{{ $photo->thumbnail()->height }}"
                        alt="{{ $photo->caption ?? "Photo" }}"
                        loading="lazy"
                        @class([
                            'rounded-box bg-base-300 w-full object-cover',
                            'aspect-square' => $leadingPhotos->count() > 1,
                            'aspect-video' => $leadingPhotos->count() === 1,
                        ])
                    />
                @endforeach
            </div>
        @endif

        @if (! $revealed)
            <p class="font-sans text-sm">
                {{ $monthForViewer->otherStatusLabel() }} · Yours: {{ $monthForViewer->viewerStatusLabel() }}
            </p>
        @elseif ($snippet() !== null)
            <p class="line-clamp-2 text-sm opacity-80">{{ $snippet() }}</p>
        @elseif ($isEmpty())
            <p class="text-sm italic opacity-60">Nothing written.</p>
        @else
            <p class="text-sm italic opacity-60">{{ $monthForViewer->other->displayName() }} didn't share one</p>
        @endif
    </div>
</a>
