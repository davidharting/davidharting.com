<x-layout.app>
    <x-breadcrumbs.container>
        <x-breadcrumbs.crumb :url="route('scorecards.create')">
            Create Scorecard
        </x-breadcrumbs.crumb>
    </x-breadcrumbs.container>

    <x-type.page-title>Create Scorecard</x-type.page-title>

    <livewire:create-scorecard />

</x-layout.app>
