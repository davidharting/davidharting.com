<div>
    <x-type.page-title>{{ $title }}</x-type.page-title>

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
