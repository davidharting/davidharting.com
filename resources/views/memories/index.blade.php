<x-layout.app>
    <div class="mx-auto max-w-xl">
        <x-type.page-title class="text-5xl">Memories</x-type.page-title>

        <div class="mt-8 space-y-4">
            @foreach ($months as $memoryMonth)
                <x-memories.month-tile :memory-month="$memoryMonth" />
            @endforeach
        </div>
    </div>
</x-layout.app>
