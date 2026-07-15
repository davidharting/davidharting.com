@use("App\Enum\MediaTypeName")

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
            MediaTypeName::Book->value => "📕",
            MediaTypeName::Movie->value => "🍿",
            MediaTypeName::Album->value => "📀",
            MediaTypeName::TvShow->value => "📺",
            MediaTypeName::VideoGame->value => "🎮",
        ];
        $currentYear = null;
        $currentMonth = null;
    @endphp

    <div class="mt-12">
        @if ($items->isEmpty())
            No items
        @else
            <div class="space-y-3">
                @foreach ($items as $item)
                    @php
                        $date = \Carbon\Carbon::parse($item->occurred_at);
                        $itemYear = $date->year;
                        $itemMonth = $date->month;
                    @endphp

                    @if ($itemYear !== $currentYear)
                        @php
                            $currentYear = $itemYear;
                            $currentMonth = null;
                        @endphp

                        <h2 class="text-xl {{ $loop->first ? "" : "mt-10" }}">
                            {{ $currentYear }}
                        </h2>
                    @endif

                    @if ($itemMonth !== $currentMonth)
                        @php
                            $currentMonth = $itemMonth;
                        @endphp

                        <h3
                            class="text-base text-base-content/70 {{ $loop->first ? "" : "mt-6" }}"
                        >
                            {{ $date->format("F") }}
                        </h3>
                    @endif

                    <div class="flex gap-2 sm:gap-4">
                        <div
                            class="w-6 sm:w-10 shrink-0 text-sm text-base-content/50 text-right tabular-nums"
                        >
                            {{ $date->format("j") }}
                        </div>
                        <div>
                            <div class="flex items-baseline gap-2">
                                <span
                                    class="text-sm"
                                    title="{{ $item->type }}"
                                >
                                    {{ $iconMap[$item->type] ?? "" }}
                                </span>
                                @if ($canViewMedia)
                                    <a
                                        href="{{ route("media.show", $item->id) }}"
                                        class="link-hover"
                                    >
                                        {{ $item->title }}
                                    </a>
                                @else
                                    <span>{{ $item->title }}</span>
                                @endif
                            </div>
                            @if ($item->creator)
                                <div class="text-sm text-base-content/60">
                                    {{ $item->creator }}
                                </div>
                            @endif

                            @if ($canSeeNote)
                                @if ($item->note)
                                    <div
                                        class="text-sm text-base-content/50 mt-1"
                                    >
                                        {{ trim($item->note) }}
                                    </div>
                                @endif

                                @if (isset($item->finished_comment) && $item->finished_comment)
                                    <div
                                        class="text-sm text-base-content/50 mt-1"
                                    >
                                        {{ trim($item->finished_comment) }}
                                    </div>
                                @endif
                            @endif

                            @if ($canAdministrate)
                                <a
                                    class="link link-neutral text-xs text-base-content/50"
                                    href="{{ route("filament.admin.resources.media.edit", $item->id) }}"
                                >
                                    Edit
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-layout.app>
