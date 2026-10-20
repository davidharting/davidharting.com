{{-- PROTOTYPE (#272): points the viewer at writing their own memory, opening editor variant A. --}}
@props([
    'memory',
    'month',
])

@if ($memory === null || $memory->isDraft())
    <a
        class="btn btn-primary font-sans"
        href="{{ route('memory-editor-prototype.show', ['month' => $month->toYearMonth(), 'variant' => 'A', 'state' => $memory === null ? 'fresh' : 'draft']) }}"
    >
        {{ $memory === null ? 'Write yours' : 'Keep writing' }}
    </a>
@endif
