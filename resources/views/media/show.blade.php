<x-layout.app :title="$media->title" :description="$media->title">
    <article class="max-w-2xl">
        <h1 class="text-2xl font-serif mb-4">{{ $media->title }}</h1>

        <dl class="space-y-2 mb-6">
            @if ($media->creator)
                <div>
                    <dt class="text-sm text-gray-600 dark:text-gray-400">
                        Creator
                    </dt>
                    <dd>{{ $media->creator->name }}</dd>
                </div>
            @endif

            <div>
                <dt class="text-sm text-gray-600 dark:text-gray-400">Type</dt>
                <dd>{{ $media->mediaType->name->value }}</dd>
            </div>

            @if ($media->year)
                <div>
                    <dt class="text-sm text-gray-600 dark:text-gray-400">
                        Year
                    </dt>
                    <dd>{{ $media->year }}</dd>
                </div>
            @endif
        </dl>

        @if ($media->note)
            <div class="card bg-base-200 mb-6">
                <div class="card-body">
                    <h3 class="card-title text-base">Note</h3>
                    <p>{{ $media->note }}</p>
                </div>
            </div>
        @endif

        <section>
            <h2 class="text-lg font-semibold mb-3">Timeline</h2>
            <ul class="space-y-3">
                @foreach ($timeline as $event)
                    <li class="flex gap-3">
                        <span
                            class="text-sm text-gray-600 dark:text-gray-400 w-24 shrink-0"
                        >
                            {{ $event["date"]->format("M j, Y") }}
                        </span>
                        <div>
                            <span class="font-medium">
                                {{ ucfirst($event["type"]) }}
                            </span>
                            @if ($event["comment"])
                                <p
                                    class="text-gray-600 dark:text-gray-400 mt-1"
                                >
                                    {{ $event["comment"] }}
                                </p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        <p class="text-sm mt-8">
            <a href="{{ route("media.index") }}" class="link">
                Back to media log
            </a>
        </p>
    </article>
</x-layout.app>
