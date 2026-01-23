<x-layout.app title="David's Media Log" description="I track what I read, watch, and play here!">
    <div>
    <x-type.page-title>Media Log</x-type.page-title>

    <form method="GET" action="{{ route('media.index') }}" class="flex flex-row space-x-2 m-2" id="media-filter-form">
        <select
            name="list"
            class="select select-sm select-ghost max-w-32"
            onchange="this.form.submit()"
        >
            <option value="finished" {{ $list === 'finished' ? 'selected' : '' }}>Finished</option>
            <option value="in-progress" {{ $list === 'in-progress' ? 'selected' : '' }}>In Progress</option>
            <option value="backlog" {{ $list === 'backlog' ? 'selected' : '' }}>Backlog</option>
        </select>

        <select
            name="year"
            class="select select-sm select-ghost max-w-32"
            @if($disableFilters) disabled @endif
            onchange="this.form.submit()"
        >
            <option value="">All Years</option>
            @foreach ($years as $yearOption)
                <option value="{{ $yearOption }}" {{ $year == $yearOption ? 'selected' : '' }}>{{ $yearOption }}</option>
            @endforeach
        </select>

        <select
            name="type"
            class="select select-sm select-ghost max-w-32"
            @if($disableFilters) disabled @endif
            onchange="this.form.submit()"
        >
            <option value="">All Types</option>
            @foreach ($mediaTypes as $mediaType)
                <option value="{{ $mediaType->value }}" {{ $type === $mediaType->value ? 'selected' : '' }}>
                    {{ $mediaType->displayName() }}
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
</x-layout.app>
