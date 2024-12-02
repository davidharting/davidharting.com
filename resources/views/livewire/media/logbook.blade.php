<div>
    <x-type.page-title>Media Log</x-type.page-title>
    <div class="mt-6">
        @if ($items->isEmpty())
            Nothing logged yet
        @else
            <div>
                <ul class="space-y-4">
                    @foreach ($items as $item)
                        <li>
                            <x-media.logbook-item :item="$item" />
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
