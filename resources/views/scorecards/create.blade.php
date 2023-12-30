<x-layout.app>
    <x-crumb.container>
        <x-crumb.item :url="route('scorecards.create')">
            Create Scorecard
        </x-crumb.item>
    </x-crumb.container>

    <x-type.page-title>Create Scorecard</x-type.page-title>

    <livewire:create-scorecard />
</x-layout.app>
