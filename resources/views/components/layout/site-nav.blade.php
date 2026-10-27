<x-layout.nav-bar label="Site">
    <x-slot:start>
        <x-nav-link :href="route('home')">Home</x-nav-link>
        <x-nav-link :href="route('notes.index')">Notes</x-nav-link>
        <x-nav-link :href="route('media.index')">Media Log</x-nav-link>
        <x-nav-link :href="route('pages.index')">Pages</x-nav-link>
    </x-slot:start>

    <x-slot:end>
        @guest
            <x-nav-link :href="route('login')">Login</x-nav-link>
        @endguest

        @can('viewAny', App\Models\Month::class)
            <x-nav-link :href="route('memories.index')">Memories</x-nav-link>
        @endcan

        @auth
            <x-nav-link :href="route('profile.edit')">Account</x-nav-link>
        @endauth
    </x-slot:end>
</x-layout.nav-bar>
