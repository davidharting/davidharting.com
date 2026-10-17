{{-- PROTOTYPE variant B: one long stream, most of each memory inline, newest month first. --}}
<x-layout.app>
    <x-type.page-title>Memories</x-type.page-title>
    <div class="mx-auto mt-8 max-w-3xl space-y-12">
        @foreach ($months as $m)
            @include('memories-prototype.B._month', ['m' => $m, 'full' => false])
        @endforeach
    </div>
    <div class="h-24"></div>
    @include('memories-prototype._switcher')
</x-layout.app>
