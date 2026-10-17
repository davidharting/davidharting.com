{{-- Plain text with paragraphs. $limit keeps only the first N paragraphs. --}}
@php
    $paragraphs = preg_split('/\n\s*\n/', trim($memory->body));
    $shown = isset($limit) ? array_slice($paragraphs, 0, $limit) : $paragraphs;
@endphp
<div class="space-y-3">
    @foreach ($shown as $paragraph)
        <p>{!! nl2br(e($paragraph)) !!}</p>
    @endforeach
</div>
@if (count($shown) < count($paragraphs))
    <p class="mt-2 text-sm italic opacity-70">{{ count($paragraphs) - count($shown) }} more paragraphs</p>
@endif
