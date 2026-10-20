{{-- A memory's plain text. Line breaks are kept as written, so a blank line reads as a new paragraph. --}}
@props([
    'memory',
])

<div {{ $attributes->merge(["class" => "whitespace-pre-line"]) }}>{{ trim($memory->body) }}</div>
