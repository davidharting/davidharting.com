<x-layout.app>
    <x-slot:nav>
        <div class="mb-8 flex w-full justify-between">
            <nav class="flex gap-1">
                <x-nav-link :href="route('home')">Home</x-nav-link>
                <x-nav-link :href="route('memories.index')"> Memories </x-nav-link>
            </nav>
            <div class="flex gap-1">
                <x-nav-link :href="route('dashboard')"> Account </x-nav-link>
            </div>
        </div>
    </x-slot:nav>

    {{ $slot }}
</x-layout.app>
