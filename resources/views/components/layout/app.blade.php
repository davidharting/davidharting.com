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
        @fluxAppearance
    </head>

    <body class="font-sans antialiased">
        <div class="container mx-auto">
            <main class="mt-8">
                <flux:navbar>
                    <flux:navbar.item
                        href="{{ route('home') }}"
                        :current="request()->routeIs('home')"
                    >
                        Home
                    </flux:navbar.item>

                    <flux:navbar.item
                        href="{{ route('notes.index') }}"
                        :current="request()->routeIs('notes.index')"
                    >
                        Notes
                    </flux:navbar.item>

                    <flux:navbar.item
                        href="{{ route('media.index') }}"
                        :current="request()->routeIs('media.index')"
                    >
                        Media Log
                    </flux:navbar.item>

                    <flux:navbar.item
                        href="{{ route('scorecards.create') }}"
                        :current="request()->routeIs('scorecards.create')"
                    >
                        Scorecards
                    </flux:navbar.item>

                    @can("administrate")
                        <flux:navbar.item
                            href="{{ route('admin.index') }}"
                        :current="request()->routeIs('admin.index')"
                        >
                            Admin
                        </flux:navbar.item>
                    @endcan
                </flux:navbar>
                <div class='mt-8'>
                    {{ $slot }}
                </div>
            </main>
        </div>
        @fluxScripts
    </body>
</html>
