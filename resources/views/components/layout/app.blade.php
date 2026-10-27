{{-- hideNav drops the site nav and page container, for full-screen pages like the memory editor. --}}
@props([
    'hideNav' => false,
])

<!DOCTYPE html>
<html
    lang="{{ str_replace("_", "-", app()->getLocale()) }}"
    @class(['compose-page' => $hideNav])
    wire:transition.navigate
>
<head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <meta name="theme-color" content="#f8f1e3" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#21110a" media="(prefers-color-scheme: dark)" />
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

    {{-- PROTOTYPE (mobile polish, #334): fade in only photos that are not already in the cache. --}}
    <script>
        document.querySelectorAll('img.photo-fade').forEach((img) => {
            if (img.complete) return;
            const hiddenAt = performance.now();
            img.classList.add('is-loading');
            const show = () => {
                if (performance.now() - hiddenAt < 100) img.style.transition = 'none';
                img.classList.remove('is-loading');
            };
            img.addEventListener('load', show, { once: true });
            img.addEventListener('error', show, { once: true });
        });
    </script>
</body>
</html>
