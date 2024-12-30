<div>
    <x-type.page-title>Media Log</x-type.page-title>

    <form class="flex flex-row space-x-2 m-2">
        <select
            name="list"
            wire:model.live="list"
            class="select select-sm select-ghost"
        >
            <option value="activity">Activity</option>
            <option value="backlog">Backlog</option>
            <option value="in-progress">In Progress</option>
        </select>

        <select wire:model.live="year" class="select select-sm select-ghost">
            <option value="">All</option>
            @foreach ($years as $year)
                <option value="{{ $year }}">{{ $year }}</option>
            @endforeach
        </select>

        <select wire:model.live="type" class="select select-sm select-ghost">
            <option value="">All Types</option>
            @foreach ($mediaTypes as $type)
                <option value="{{ $type->value }}">
                    {{ $type->displayName() }}
                </option>
            @endforeach
        </select>
    </form>

    <div class="my-6">
        @if ($items->isEmpty())
            No items
        @else
            <div>
                <ul class="space-y-4">
                    @foreach ($items as $item)
                        <li>
                            <x-media.item :item="$item" />
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
