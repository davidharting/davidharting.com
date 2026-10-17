<div class="flex items-center justify-between font-sans text-sm">
    <a class="link" href="{{ route('memories-prototype.index', ['variant' => $variant]) }}">← All months</a>
    <span class="flex gap-4">
        @if ($older)
            <a
                class="link"
                href="{{ route('memories-prototype.show', ['month' => $older['slug'], 'variant' => $variant]) }}"
            >Older</a>
        @endif
        @if ($newer)
            <a
                class="link"
                href="{{ route('memories-prototype.show', ['month' => $newer['slug'], 'variant' => $variant]) }}"
            >Newer</a>
        @endif
    </span>
</div>
