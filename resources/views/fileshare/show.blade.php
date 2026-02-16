<x-layout.app>
    <x-type.page-title>File Details</x-type.page-title>

    <ul>
        <li>Disk: {{ $disk ?? 'default (private)' }}</li>
        <li>Size: {{ $size }}</li>
        @if ($url)
            <li>Public URL: <a href="{{ $url }}" class="link" target="_blank">{{ $url }}</a></li>
        @endif
        @if ($temporaryUrl)
            <li>Temporary URL (5 min): <a href="{{ $temporaryUrl }}" class="link" target="_blank">{{ Str::limit($temporaryUrl, 80) }}</a></li>
        @endif
    </ul>
</x-layout.app>
