<div class="mb-8 flex w-full flex-wrap justify-between">
    <nav class="flex gap-1">
        <x-nav-link :href="route('home')">Home</x-nav-link>
        <x-nav-link :href="route('notes.index')"> Notes </x-nav-link>
        <x-nav-link :href="route('media.index')"> Media Log </x-nav-link>
        <x-nav-link :href="route('pages.index')"> Pages </x-nav-link>
    </nav>
    <div class="flex gap-1">
        @guest
            <x-nav-link :href="route('login')"> Login </x-nav-link>
        @endguest

        @can('viewAny', App\Models\Month::class)
            <x-nav-link :href="route('memories.index')"> Memories </x-nav-link>
        @endcan

        @auth
            <x-nav-link :href="route('dashboard')"> Account </x-nav-link>
        @endauth
    </div>
</div>
