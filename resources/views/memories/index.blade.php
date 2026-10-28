<x-layout.memories>
    <div class="mx-auto max-w-xl" data-memories-page="list">
        <div class="flex items-center gap-3">
            <x-type.page-title class="text-5xl">Memories</x-type.page-title>
            <x-memories.how-it-works />
        </div>

        <div class="mt-8 space-y-4">
            @foreach ($monthsForViewer as $monthForViewer)
                <x-memories.month-tile
                    :month-for-viewer="$monthForViewer"
                    :page-version="$pageVersions[$monthForViewer->month->calendarMonth->toDateString()]"
                />
            @endforeach
        </div>
    </div>
</x-layout.memories>
