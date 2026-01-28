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
    </head>

    <body class="font-serif antialiased">
        <div class="container mx-auto px-4">
            <main class="mt-8">
                <div class="flex justify-between w-full">
                    <div class="flex justify-start space-x-8 mb-8">
                        <a
                            href="{{ route("home") }}"
                            wire:navigate
                            class="link link-primary"
                        >
                            Home
                        </a>
                        <a
                            href="{{ route("notes.index") }}"
                            wire:navigate
                            class="link link-primary"
                        >
                            Notes
                        </a>
                        <a
                            href="{{ route("media.index") }}"
                            wire:navigate
                            class="link link-primary"
                        >
                            Media Log
                        </a>
                        <a
                            href="{{ route("pages.index") }}"
                            wire:navigate
                            class="link link-primary"
                        >
                            Pages
                        </a>
                    </div>
                    <div>
                        @guest
                            <a
                                href="{{ route("login") }}"
                                wire:navigate
                                class="link link-primary"
                            >
                                Login
                            </a>
                        @endguest

                        @can("administrate")
                            <a
                                href="{{ route("admin.index") }}"
                                wire:navigate
                                class="link link-primary"
                            >
                                Admin
                            </a>
                        @endcan
                    </div>
                </div>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
