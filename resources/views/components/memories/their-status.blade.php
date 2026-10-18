{{-- Where the other person's memory stands, as far as the viewer may know. Their drafts stay hidden once the month has revealed. --}}
@props([
    'memoryMonth',
])

@php
    $name = $memoryMonth->other->displayName();
    $theirs = $memoryMonth->theirs;
@endphp

@if (! $memoryMonth->isRevealed())
    @if ($theirs === null)
        {{ $name }} hasn't started yet
    @elseif ($theirs->isDraft())
        {{ $name }} started on {{ \App\Support\Month::dayLabel($theirs->created_at) }}
    @else
        {{ $name }} shared on {{ \App\Support\Month::dayLabel($theirs->shared_at) }}
    @endif
@elseif ($theirs?->isShared())
    Shared {{ \App\Support\Month::dayLabel($theirs->shared_at) }}
@else
    {{ $name }} didn't share one
@endif
