<x-layout.app>
    <x-crumb.container>
        <x-crumb.item :url="route('scorecards.create')">
            Scorecards
        </x-crumb.item>
        <x-crumb.item :url="route('scorecards.show', $scorecard)">
            {{ $scorecard->title }}
        </x-crumb.item>
    </x-crumb.container>

    <x-type.page-title>{{ $scorecard->title }}</x-type.page-title>
    <p>Started on {{ $scorecard->created_at->format("Y F j") }}</p>

    <div class="mt-12">
        <livewire:scorecards.detail :scorecard="$scorecard" />
    </div>
</x-layout.app>
