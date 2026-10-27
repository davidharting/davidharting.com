<x-layout.app>
    <div class="mx-auto max-w-xl">
        <livewire:memories.list-heading :any-month-revealed="$anyMonthRevealed" />

        <div class="mt-8 space-y-4">
            @foreach ($monthsForViewer as $monthForViewer)
                <x-memories.month-tile :month-for-viewer="$monthForViewer" />
            @endforeach
        </div>
    </div>
</x-layout.app>
