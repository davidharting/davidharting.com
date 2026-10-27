<x-layout.memories>
    <div class="mx-auto max-w-xl">
        <div class="flex items-center gap-3">
            <x-type.page-title class="text-5xl">Memories</x-type.page-title>
            <x-memories.how-it-works />
        </div>

        <div class="mt-8 space-y-4">
            @foreach ($monthsForViewer as $monthForViewer)
                <x-memories.month-tile :month-for-viewer="$monthForViewer" />
            @endforeach
        </div>

        <p class="mt-10 text-center font-sans text-sm">
            <a class="link inline-block py-2 opacity-70" href="{{ route("memories.earlier.show") }}"
                >Write about an earlier month</a>
        </p>
    </div>
</x-layout.memories>
