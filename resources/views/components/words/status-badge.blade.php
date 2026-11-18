@use(App\Enum\LearningStatus)

@props(['status'])

@php
    $tone = match ($status) {
        LearningStatus::New => 'badge-ghost',
        LearningStatus::Learning => 'badge-info',
        LearningStatus::Troublesome => 'badge-warning',
        LearningStatus::Mastered => 'badge-success',
    };
@endphp

<span {{ $attributes->class(["badge badge-soft", $tone]) }}>{{ $status->label() }}</span>
