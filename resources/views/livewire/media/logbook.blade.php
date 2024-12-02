<div>
    <x-type.page-title>Media Log</x-type.page-title>
    @if (empty($items))
        Nothing logged yet
    @else
        <div class="prose">
            <ul>
                @foreach ($items as $item)
                    <li>
                        <x-media.logbook-item :item="$item" />
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
