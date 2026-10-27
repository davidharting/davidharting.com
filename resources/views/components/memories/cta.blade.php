{{-- Opens the viewer's editor for the month: "Write yours" before they've started, "Keep writing" on a draft. Nothing once shared. --}}
@props([
    'memory',
    'month',
    'quiet' => false,
])

@if ($memory === null || $memory->isDraft())
    <a
        {{ $attributes->class(["btn font-sans", "btn-primary" => ! $quiet, "btn-ghost btn-sm" => $quiet]) }}
        href="{{ route("memories.write", $month->toYearMonth()) }}"
    >
        {{ $memory === null ? "Write yours" : "Keep writing" }}
    </a>
@endif
