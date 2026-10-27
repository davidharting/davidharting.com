{{-- hideNav drops the site nav and page container, for full-screen pages like the memory editor. --}}
@props([
    'hideNav' => false,
])

<!DOCTYPE html>
<html lang="{{ str_replace("_", "-", app()->getLocale()) }}" @class(['compose-page' => $hideNav])>
<head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <meta name="theme-color" content="#f8f1e3" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#21110a" media="(prefers-color-scheme: dark)" />
    @head

    <script type="speculationrules">
        {
            "prefetch": [
                {
                    "where": {
                        "and": [
                            { "href_matches": "/memories/*" },
                            { "not": { "href_matches": "/memories/*/write" } },
                            { "not": { "href_matches": "/memories/photos/*" } }
                        ]
                    },
                    "eagerness": "moderate"
                }
            ]
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-serif antialiased">
    @if ($hideNav)
        {{ $slot }}
    @else
        <div class="container mx-auto px-4">
            <main class="mt-8">
                @isset($nav)
                    {{ $nav }}
                @else
                    <x-layout.site-nav />
                @endisset
                {{ $slot }}
            </main>
        </div>
    @endif
</body>
</html>
