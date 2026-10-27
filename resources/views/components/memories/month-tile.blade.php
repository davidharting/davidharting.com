@php
    $month = $monthForViewer->month->calendarMonth;
    $revealed = $monthForViewer->month->hasRevealed();
    $leadingPhotos = $thumbnails();
    $monthUrl = route('memories.show', $month->toYearMonth());
    $writeUrl = route('memories.write', $month->toYearMonth());
@endphp

@if ($isQuietLine())
    <div class="flex items-center justify-between gap-4 px-4 py-1 opacity-70">
        <a href="{{ $monthUrl }}" class="text-lg">{{ $month->label() }}</a>
        <a class="link link-hover font-sans text-sm" href="{{ $writeUrl }}">{{ $writeLabel() }}</a>
    </div>
@else
    <div @class([
        'card relative transition-shadow hover:shadow-md',
        'bg-base-200' => $revealed,
        'bg-accent/20' => ! $revealed,
    ])>
        <div class="card-body gap-3 p-4">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-2xl">
                    <a href="{{ $monthUrl }}" class="after:absolute after:inset-0">{{ $month->label() }}</a>
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
                                class="h-full w-full object-cover text-transparent"
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
                <a class="link link-hover relative self-start font-sans text-sm opacity-70" href="{{ $writeUrl }}">
                    {{ $writeLabel() }}
                </a>
            @endif
        </div>
    </div>
@endif
