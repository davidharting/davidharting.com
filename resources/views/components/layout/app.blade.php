@props([
    "title",
    "description",
])

<!DOCTYPE html>
<html lang="{{ str_replace("_", "-", app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />

        @php
            $pageTitle = $title ?? "David Harting's Website";
            $pageDescription = $description ?? "David's Corner of the Internet";
        @endphp

        <title>{{ $pageTitle }}</title>
        <meta name="title" content="{{ $pageTitle }}" />

        <meta name="description" content="{{ $pageDescription }}" />
        <meta property="og:description" content="{{ $pageDescription }}" />

        @vite(["resources/css/app.css", "resources/js/app.js"])

        <x-feed-links />

        {{-- Optional slot for page-specific <head> content (e.g., extra meta tags, structured data, page-specific styles) --}}
        {{ $head ?? "" }}
    </head>

    <body class="font-serif antialiased" hx-ext="preload">
        <div class="container mx-auto px-4">
            <main class="mt-8">
                <div class="flex justify-between w-full mb-8">
                    <nav class="flex gap-1">
                        <x-nav-link :href="route('home')" route="home">
                            Home
                        </x-nav-link>
                        <x-nav-link
                            :href="route('notes.index')"
                            route="notes.*"
                        >
                            Notes
                        </x-nav-link>
                        <x-nav-link
                            :href="route('media.index')"
                            route="media.*"
                        >
                            Media Log
                        </x-nav-link>
                        <x-nav-link
                            :href="route('pages.index')"
                            route="pages.*"
                        >
                            Pages
                        </x-nav-link>
                    </nav>
                    <div class="flex gap-1">
                        @guest
                            <x-nav-link
                                :href="route('login')"
                                route="login"
                                :preload="false"
                            >
                                Login
                            </x-nav-link>
                        @endguest

                        @can("administrate")
                            <x-nav-link
                                :href="route('admin.index')"
                                route="admin.*"
                                :preload="false"
                            >
                                Admin
                            </x-nav-link>
                        @endcan
                    </div>
                </div>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
