<x-layout.app>
    <div class="mx-auto max-w-xl">
        <x-type.page-title class="text-5xl">Memories</x-type.page-title>

        <div class="mt-8 space-y-4">
            @foreach ($months as $monthOfMemories)
                <x-memories.month-tile :month-of-memories="$monthOfMemories" />
            @endforeach
        </div>
    </div>
</x-layout.app>
