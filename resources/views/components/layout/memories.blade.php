<x-layout.app>
    <x-slot:nav>
        <x-layout.memories-nav />
    </x-slot:nav>

    {{ $slot }}

    @livewireScripts
</x-layout.app>
