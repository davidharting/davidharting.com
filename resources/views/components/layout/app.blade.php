<!DOCTYPE html>
<html lang="{{ str_replace("_", "-", app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    @head

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-serif antialiased">
    <div class="container mx-auto px-4">
        <main class="mt-8">
            <div class="mb-8 flex w-full justify-between">
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

                    @can('administrate')
                        <x-nav-link :href="route('admin.index')"> Admin </x-nav-link>
                    @endcan
                </div>
            </div>
            {{ $slot }}
        </main>
    </div>
</body>
</html>
