<x-layout.app transitions-between-pages>
    <x-slot:nav>
        <x-layout.memories-nav />
    </x-slot:nav>

    {{ $slot }}

    @livewireStyles
    @livewireScripts
</x-layout.app>
