<x-layout.app>
    <x-type.page-title>{{ $scorecard->title }}</x-type.page-title>

    <div class="mt-12">
        <livewire:scorecards.detail :scorecard="$scorecard" />
    </div>
</x-layout.app>
