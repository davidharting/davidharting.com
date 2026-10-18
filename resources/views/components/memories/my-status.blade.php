@props([
    'memory',
])

@if ($memory === null)
    Not started
@elseif ($memory->isDraft())
    Draft, started {{ \App\Support\Month::dayLabel($memory->created_at) }}
@else
    Shared {{ \App\Support\Month::dayLabel($memory->shared_at) }}
@endif
