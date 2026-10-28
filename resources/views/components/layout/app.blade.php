{{-- hideNav drops the site nav and page container, for full-screen pages like the memory editor. --}}
@props([
    'hideNav' => false,
])

<!DOCTYPE html>
<html lang="{{ str_replace("_", "-", app()->getLocale()) }}" wire:transition.navigate>
<head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    @head

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
