<div>
    <x-type.page-title>{{ $title }}</x-type.page-title>

    <form class="form-control flex flex-row space-x-2 m-2">
        <select wire:model="year" class="select select-sm select-ghost">
            <option value="null">All Years</option>
            @foreach ($this->years as $year)
                <option value="{{ $year }}">{{ $year }}</option>
            @endforeach
        </select>

        <select wire:model="type" class="select select-sm selct-ghost">
            <option>All Media</option>
            @foreach (\App\Enum\MediaTypeName::cases() as $mediaType)
                <option value="{{ $mediaType }}">
                    {{ $mediaType->displayName() }}
                </option>
            @endforeach
        </select>
    </form>

    @can("view-backlog")
        <ul class="tabs tabs-boxed mt-2 max-w-96" role="tablist">
            <a
                wire:navigate
                href="{{ route("media.logbook.show") }}"
                @class(["tab", "tab-active" => request()->routeIs("media.logbook.show")])
            >
                Activity
            </a>
            <a
                wire:navigate
                href="{{ route("media.backlog.show") }}"
                @class(["tab", "tab-active" => request()->routeIs("media.backlog.show")])
                role="tab"
            >
                Backlog
            </a>
        </ul>
    @endcan

    <div class="my-6">
        @if ($items->isEmpty())
            No items
        @else
            <div>
                <ul class="space-y-4">
                    @foreach ($items as $item)
                        <li>
                            <x-dynamic-component
                                :component="$itemComponent"
                                :item="$item"
                            />
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
