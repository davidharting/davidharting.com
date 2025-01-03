<!DOCTYPE html>
<html lang="{{ str_replace("_", "-", app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />

        <title>{{ config("app.name", "Laravel") }}</title>

        @vite(["resources/css/app.css", "resources/js/app.js"])
    </head>

    <body class="font-sans antialiased">
        <div class="container mx-auto">
            <main class="mt-8">
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
                        href="{{ route("scorecards.create") }}"
                        wire:navigate
                        class="link link-primary"
                    >
                        Scorecards
                    </a>
                </div>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
