<x-layout.app>
    <x-slot:nav>
        <x-layout.memories-nav />
    </x-slot:nav>

    {{ $slot }}

    @livewireStyles
    @livewireScripts
</x-layout.app>
