<x-layout.app title="Pages" description="All pages on davidharting.com">
    <x-type.page-title>Pages</x-type.page-title>

    @if ($pages->isEmpty())
        <p class="mt-4">No pages yet</p>
    @else
        <ul class="mt-8 space-y-2">
            @foreach ($pages as $page)
                <li>
                    <a
                        href="{{ route("pages.show", $page->slug) }}"
                        class="link link-primary"
                    >
                        {{ $page->title }}
                    </a>
                    @if (! $page->is_published)
                        <span
                            class="ml-2 inline-block rounded bg-gray-200 px-2 py-1 text-xs font-medium text-gray-700"
                        >
                            Unpublished
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-layout.app>
