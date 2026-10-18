{{-- One month in the list. Before the reveal it says where both memories stand; after, it opens with a line of what the viewer can read. --}}
@props([
    'memoryMonth',
])

@php
    $other = $memoryMonth->other;
    $mine = $memoryMonth->mine;
    $revealed = $memoryMonth->isRevealed();
    $snippet = $revealed ? ($memoryMonth->readableTheirs() ?? ($mine?->isShared() ? $mine : null)) : null;
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
                <x-memories.initial
                    :memory="$revealed && $memoryMonth->theirs?->isDraft() ? null : $memoryMonth->theirs"
                    :name="$other->displayName()"
                />
                <x-memories.initial :memory="$mine" :name="$memoryMonth->viewer->displayName()" />
            </div>
        </div>

        @if (! $revealed)
            <p class="font-sans text-sm">
                <x-memories.their-status :memory-month="$memoryMonth" />
                · Yours:
                <x-memories.my-status :memory="$mine" />
            </p>
        @elseif ($snippet)
            <p class="line-clamp-2 text-sm opacity-80">
                {{ \Illuminate\Support\Str::of($snippet->body)->trim()->before("\n") }}
            </p>
        @elseif ($memoryMonth->isEmpty())
            <p class="text-sm italic opacity-60">Nothing written.</p>
        @else
            <p class="text-sm italic opacity-60"><x-memories.their-status :memory-month="$memoryMonth" /></p>
        @endif
    </div>
</a>
