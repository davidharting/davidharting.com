<div class='breadcrumbs'>
    <ul>
        <x-breadcrumbs.crumb :url="route('home')">Home</x-breadcrumbs.crumb>
        {{ $slot }}
    </ul>
</div>
