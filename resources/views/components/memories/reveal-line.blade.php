{{-- When the month revealed, or what it is waiting on. --}}
@props([
    'memoryMonth',
])

@php
    $name = $memoryMonth->other->displayName();
    $revealedAt = $memoryMonth->revealedAt();
    $deadline = \App\Support\Month::dayLabel($memoryMonth->month->revealDeadline());
@endphp

@if ($revealedAt === null)
    @if ($memoryMonth->theirs?->isShared() && ! $memoryMonth->mine?->isShared())
        Share yours to reveal both.
    @elseif ($memoryMonth->mine?->isShared())
        Reveals when {{ $name }} shares, or on {{ $deadline }} at the latest.
    @else
        Reveals when you've both shared, or on {{ $deadline }} at the latest.
    @endif
@elseif ($memoryMonth->revealedAtDeadline())
    Revealed {{ \App\Support\Month::dayLabel($revealedAt) }}, at the deadline.
@else
    Revealed {{ \App\Support\Month::dayLabel($revealedAt) }}, when you'd both shared.
@endif
