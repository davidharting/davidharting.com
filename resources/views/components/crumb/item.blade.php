@props(['url'])

<li>
    <a href='{{ $url }}' wire:navigate>{{ $slot }}</a>
</li>
