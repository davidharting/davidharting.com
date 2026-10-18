{{-- One month in the list. Before the reveal it says where both memories stand; after, it opens with a line of what the viewer can read. --}}
@props([
    'memoryMonth',
])

@php
    $revealed = $memoryMonth->isRevealed();
    $snippet = $memoryMonth->snippet();
@endphp

<a
    href="{{ route("memories.show", $memoryMonth->month->toYearMonth()) }}"
    @class([
        'card block transition-shadow hover:shadow-md',
        'bg-base-200' => $revealed,
        'bg-accent/20' => ! $revealed,
    ])
>
    <div class="card-body gap-3 p-4">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-2xl">{{ $memoryMonth->month->label() }}</h2>
            <div class="flex gap-1">
                <x-memories.initial :memory="$memoryMonth->theirs" :name="$memoryMonth->other->displayName()" />
                <x-memories.initial :memory="$memoryMonth->mine" :name="$memoryMonth->viewer->displayName()" />
            </div>
        </div>

        @if (! $revealed)
            <p class="font-sans text-sm">
                {{ $memoryMonth->theirStatus() }} · Yours:
                <x-memories.my-status :memory="$memoryMonth->mine" />
            </p>
        @elseif ($snippet !== null)
            <p class="line-clamp-2 text-sm opacity-80">{{ $snippet }}</p>
        @elseif ($memoryMonth->isEmpty())
            <p class="text-sm italic opacity-60">Nothing written.</p>
        @else
            <p class="text-sm italic opacity-60">{{ $memoryMonth->other->displayName() }} didn't share one</p>
        @endif
    </div>
</a>
