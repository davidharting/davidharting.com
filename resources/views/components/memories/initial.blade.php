{{-- A person's initial in a circle: filled once shared, dashed while a draft, faint before it is started. --}}
@props([
    'memory',
    'name',
])

@php
    $state = match (true) {
        $memory === null => 'not started',
        $memory->isDraft() => 'draft',
        default => 'shared',
    };
@endphp

<span
    title="{{ $name }}: {{ $state }}"
    aria-label="{{ $name }}: {{ $state }}"
    @class([
        'inline-flex h-9 w-9 items-center justify-center rounded-full font-sans text-sm',
        'bg-primary text-primary-content' => $state === 'shared',
        'border-warning border-2 border-dashed' => $state === 'draft',
        'border-base-300 border opacity-50' => $state === 'not started',
    ])
>{{ mb_substr($name, 0, 1) }}</span>
