<x-layout.nav-bar label="Memories">
    <x-slot:start>
        <x-nav-link :href="route('home')">Home</x-nav-link>
        <x-nav-link :href="route('memories.index')"> Memories </x-nav-link>
    </x-slot:start>

    <x-slot:end>
        <x-nav-link :href="route('profile.edit')"> Account </x-nav-link>
    </x-slot:end>
</x-layout.nav-bar>
