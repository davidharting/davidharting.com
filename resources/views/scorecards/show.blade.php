<x-layout.app>
    <x-breadcrumbs.container>
        <x-breadcrumbs.crumb :url="route('scorecards.create')">
            Scorecards
        </x-breadcrumbs.crumb>
        <x-breadcrumbs.crumb :url="route('scorecards.show', $scorecard)">
            {{ $scorecard->title }}
        </x-breadcrumbs.crumb>
    </x-breadcrumbs.container>

    <x-type.page-title>{{ $scorecard->title }}</x-type.page-title>
    <p>Started on {{ $scorecard->created_at->format('Y F j') }}</p>

    <div class="mt-12">
        <livewire:scorecards.detail :scorecard="$scorecard" />
    </div>
</x-layout.app>
