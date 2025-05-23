<x-layout.app
    title="Scorecard for {{ $scorecard->title }}"
    description="Track scores for {{ $scorecard->title }}"
>
    <x-type.page-title>{{ $scorecard->title }}</x-type.page-title>
    <p>Started on {{ $scorecard->created_at->format("Y F j") }}</p>

    <div class="mt-12">
        <livewire:scorecards.detail :scorecard="$scorecard" />
    </div>
</x-layout.app>
