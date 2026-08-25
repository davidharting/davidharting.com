<x-layout.app>
    <article class="max-w-2xl">
        <h1 class="mb-4 font-serif text-2xl">{{ $media->title }}</h1>

        <dl class="mb-6 space-y-2">
            @if ($media->creator)
                <div>
                    <dt class="text-sm text-gray-600 dark:text-gray-400">Creator</dt>
                    <dd>{{ $media->creator->name }}</dd>
                </div>
            @endif

            <div>
                <dt class="text-sm text-gray-600 dark:text-gray-400">Type</dt>
                <dd>{{ $media->mediaType->name->value }}</dd>
            </div>

            @if ($media->year)
                <div>
                    <dt class="text-sm text-gray-600 dark:text-gray-400">Year</dt>
                    <dd>{{ $media->year }}</dd>
                </div>
            @endif
        </dl>

        <section>
            <h2 class="mb-3 text-lg font-semibold">Timeline</h2>
            <ul class="space-y-3">
                @foreach ($timeline as $event)
                    <li class="flex gap-3">
                        <span class="w-24 shrink-0 text-sm text-gray-600 dark:text-gray-400">
                            {{ $event["date"]->format("M j, Y") }}
                        </span>
                        <div>
                            <span class="font-medium"> {{ ucfirst($event["type"]) }} </span>
                            @if ($event['comment'])
                                <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $event["comment"] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        <p class="mt-8 text-sm">
            <a href="{{ route("media.index") }}" class="link"> Back to media log </a>
        </p>
    </article>
</x-layout.app>
