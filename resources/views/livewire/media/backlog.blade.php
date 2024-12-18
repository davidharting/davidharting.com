<div>
    <x-type.page-title>Media Backlog</x-type.page-title>
    <div class="mt-6">
        @if ($items->isEmpty())
            No backlog items
        @else
            <div>
                <ul class="space-y-4">
                    @foreach ($items as $item)
                        <li>
                            <x-media.backlog-item :item="$item" />
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
