<x-layout.app
    title="David's Media Log"
    description="I track what I read, watch, and play here!"
>
    <x-type.page-title>Media Log</x-type.page-title>

    <form
        class="flex flex-row space-x-2 m-2"
        method="GET"
        action="{{ route("media.index") }}"
    >
        <select
            name="list"
            class="select select-sm select-ghost max-w-32"
            onchange="this.form.submit()"
        >
            <option value="finished" @selected($list === "finished")>
                Finished
            </option>
            <option value="in-progress" @selected($list === "in-progress")>
                In Progress
            </option>
            <option value="backlog" @selected($list === "backlog")>
                Backlog
            </option>
        </select>

        <select
            name="year"
            class="select select-sm select-ghost max-w-32"
            @disabled($disableFilters)
            onchange="this.form.submit()"
        >
            <option value="">All Years</option>
            @foreach ($years as $yearOption)
                <option
                    value="{{ $yearOption }}"
                    @selected($year === (string) $yearOption)
                >
                    {{ $yearOption }}
                </option>
            @endforeach
        </select>

        <select
            name="type"
            class="select select-sm select-ghost max-w-32"
            @disabled($disableFilters)
            onchange="this.form.submit()"
        >
            <option value="">All Types</option>
            @foreach ($mediaTypes as $mediaType)
                <option
                    value="{{ $mediaType->value }}"
                    @selected($type === $mediaType->value)
                >
                    {{ $mediaType->displayName() }}
                </option>
            @endforeach
        </select>

        @if ($disableFilters)
            <input type="hidden" name="year" value="{{ $year }}" />
            <input type="hidden" name="type" value="{{ $type }}" />
        @endif
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
</x-layout.app>
