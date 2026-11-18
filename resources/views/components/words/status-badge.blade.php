@props(['status'])

@php
    $tone = match ($status) {
        \App\Enum\LearningStatus::New => 'badge-ghost',
        \App\Enum\LearningStatus::Learning => 'badge-info',
        \App\Enum\LearningStatus::Troublesome => 'badge-warning',
        \App\Enum\LearningStatus::Mastered => 'badge-success',
    };
@endphp

<span {{ $attributes->class(["badge badge-soft", $tone]) }}>{{ $status->label() }}</span>
