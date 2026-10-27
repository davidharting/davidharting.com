@props([
    'label',
])

<nav aria-label="{{ $label }}" class="mb-8 flex w-full flex-wrap justify-between">
    <div class="flex gap-1">{{ $start }}</div>
    <div class="flex gap-1">{{ $end }}</div>
</nav>
