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

    <body class="font-serif antialiased">
        <div class="container mx-auto px-4">
            <main class="mt-8">
                <div class="flex justify-between w-full mb-8">
                    <nav class="flex gap-1">
                        <x-nav-link
                            :href="route('home')"
                            active-pattern="home"
                        >
                            Home
                        </x-nav-link>
                        <x-nav-link
                            :href="route('notes.index')"
                            active-pattern="notes.*"
                        >
                            Notes
                        </x-nav-link>
                        <x-nav-link
                            :href="route('media.index')"
                            active-pattern="media.*"
                        >
                            Media Log
                        </x-nav-link>
                        <x-nav-link
                            :href="route('pages.index')"
                            active-pattern="pages.*"
                        >
                            Pages
                        </x-nav-link>
                    </nav>
                    <div class="flex gap-1">
                        @guest
                            <x-nav-link
                                :href="route('login')"
                                active-pattern="login"
                            >
                                Login
                            </x-nav-link>
                        @endguest

                        @can("administrate")
                            <x-nav-link
                                :href="route('admin.index')"
                                active-pattern="admin.*"
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
