{{-- A memory's plain text: blank lines separate paragraphs, single line breaks are kept. Never Markdown. --}}
@props([
    'memory',
])

<div {{ $attributes->merge(["class" => "space-y-4"]) }}>
    @foreach (preg_split('/\n\s*\n/', trim($memory->body)) as $paragraph)
        <p>{!! nl2br(e($paragraph)) !!}</p>
    @endforeach
</div>
