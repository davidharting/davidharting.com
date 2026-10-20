{{-- Points the viewer at writing their own memory. The editor doesn't exist yet, so the button is a disabled placeholder. --}}
@props([
    'memory',
])

@if ($memory === null || $memory->isDraft())
    <button type="button" class="btn btn-primary" disabled>
        {{ $memory === null ? "Write yours" : "Keep writing" }}
    </button>
@endif
