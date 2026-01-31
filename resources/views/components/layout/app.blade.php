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
                        <a
                            href="{{ route("home") }}"
                            class="btn btn-ghost btn-sm relative {{ request()->routeIs("home") ? "text-primary" : "" }}"
                            preload="mouseover"
                        >
                            Home
                            @if (request()->routeIs("home"))
                                <span
                                    class="absolute bottom-0 left-2 right-2 h-0.5 bg-primary rounded"
                                    style="view-transition-name: nav-indicator"
                                ></span>
                            @endif
                        </a>
                        <a
                            href="{{ route("notes.index") }}"
                            class="btn btn-ghost btn-sm relative {{ request()->routeIs("notes.*") ? "text-primary" : "" }}"
                            preload="mouseover"
                        >
                            Notes
                            @if (request()->routeIs("notes.*"))
                                <span
                                    class="absolute bottom-0 left-2 right-2 h-0.5 bg-primary rounded"
                                    style="view-transition-name: nav-indicator"
                                ></span>
                            @endif
                        </a>
                        <a
                            href="{{ route("media.index") }}"
                            class="btn btn-ghost btn-sm relative {{ request()->routeIs("media.*") ? "text-primary" : "" }}"
                            preload="mouseover"
                        >
                            Media Log
                            @if (request()->routeIs("media.*"))
                                <span
                                    class="absolute bottom-0 left-2 right-2 h-0.5 bg-primary rounded"
                                    style="view-transition-name: nav-indicator"
                                ></span>
                            @endif
                        </a>
                        <a
                            href="{{ route("pages.index") }}"
                            class="btn btn-ghost btn-sm relative {{ request()->routeIs("pages.*") ? "text-primary" : "" }}"
                            preload="mouseover"
                        >
                            Pages
                            @if (request()->routeIs("pages.*"))
                                <span
                                    class="absolute bottom-0 left-2 right-2 h-0.5 bg-primary rounded"
                                    style="view-transition-name: nav-indicator"
                                ></span>
                            @endif
                        </a>
                    </nav>
                    <div class="flex gap-1">
                        @guest
                            <a
                                href="{{ route("login") }}"
                                class="btn btn-ghost btn-sm"
                            >
                                Login
                            </a>
                        @endguest

                        @can("administrate")
                            <a
                                href="{{ route("admin.index") }}"
                                class="btn btn-ghost btn-sm relative {{ request()->routeIs("admin.*") ? "text-primary" : "" }}"
                            >
                                Admin
                                @if (request()->routeIs("admin.*"))
                                    <span
                                        class="absolute bottom-0 left-2 right-2 h-0.5 bg-primary rounded"
                                        style="
                                            view-transition-name: nav-indicator;
                                        "
                                    ></span>
                                @endif
                            </a>
                        @endcan
                    </div>
                </div>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
