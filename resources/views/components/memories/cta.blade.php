{{-- Opens the viewer's editor for the month: "Write yours" before they've started, "Keep writing" on a draft. Nothing once shared. --}}
@props([
    'memory',
    'month',
])

@if ($memory === null || $memory->isDraft())
    <a class="btn btn-primary font-sans" href="{{ route("memories.write", $month->toYearMonth()) }}">
        {{ $memory === null ? "Write yours" : "Keep writing" }}
    </a>
@endif
