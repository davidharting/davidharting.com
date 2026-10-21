{{-- A full-screen page with no site navigation, for writing. --}}
<!DOCTYPE html>
<html lang="{{ str_replace("_", "-", app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    @head

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-base-100 font-serif antialiased">
    {{ $slot }}
</body>
</html>
