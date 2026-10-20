@php
    $month = $monthForViewer->month;
    $reveal = $monthForViewer->reveal;
@endphp

<a
    href="{{ route("memories.show", $month->toYearMonth()) }}"
    @class([
        'card block transition-shadow hover:shadow-md',
        'bg-base-200' => $reveal->hasHappened(),
        'bg-accent/20' => ! $reveal->hasHappened(),
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

        @if (! $reveal->hasHappened())
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
