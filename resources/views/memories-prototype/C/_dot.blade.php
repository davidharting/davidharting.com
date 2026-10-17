{{-- An initial in a circle, filled when shared, dashed when a draft, faint when nothing. --}}
<span
    title="{{ $label }}"
    @class([
        'inline-flex h-9 w-9 items-center justify-center rounded-full font-sans text-sm',
        'bg-primary text-primary-content' => $memory?->isShared(),
        'border-2 border-dashed border-warning' => $memory?->isDraft(),
        'border border-base-300 opacity-50' => ! $memory,
    ])
>{{ $initial }}</span>
