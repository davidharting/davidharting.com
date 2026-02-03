@use('App\Enum\MediaTypeName')

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

    @php
        $iconMap = [
            MediaTypeName::Book->value => '📕',
            MediaTypeName::Movie->value => '🍿',
            MediaTypeName::Album->value => '📀',
            MediaTypeName::TvShow->value => '📺',
            MediaTypeName::VideoGame->value => '🎮',
        ];
    @endphp

    <div class="my-6">
        @if ($items->isEmpty())
            No items
        @else
            <div>
                <ul class="space-y-4">
                    @foreach ($items as $item)
                        <li>
                            <div>
                                <div class="text-sm text-base-content/60">{{ $item->formatted_date }}</div>
                                <div>
                                    <span>{{ $iconMap[$item->type] ?? '' }}</span>
                                    @if ($canViewMedia)
                                        <a
                                            href="{{ route("media.show", $item->id) }}"
                                            class="link link-hover"
                                        >
                                            {{ $item->title }}
                                        </a>
                                    @else
                                        <span>{{ $item->title }}</span>
                                    @endif
                                </div>
                                <div class="text-sm">
                                    {{ $item->creator }}
                                </div>
                                @if ($canSeeNote)
                                    @if ($item->note)
                                        <div class="text-xs text-base-content/60">
                                            {{ trim($item->note) }}
                                        </div>
                                    @endif
                                    @if (isset($item->finished_comment) && $item->finished_comment)
                                        <div class="text-xs text-base-content/60">
                                            {{ trim($item->finished_comment) }}
                                        </div>
                                    @endif
                                @endif
                                @if ($canAdministrate)
                                    <a
                                        class="link link-neutral text-xs text-base-content/60"
                                        href="{{ route("filament.admin.resources.media.edit", $item->id) }}"
                                    >
                                        Edit
                                    </a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-layout.app>
