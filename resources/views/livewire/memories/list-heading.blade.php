<div x-data="{ sheetOpen: false }">
    <div class="flex items-center gap-3">
        <x-type.page-title class="text-5xl">Memories</x-type.page-title>

        @unless ($this->showsCard)
            <button
                type="button"
                class="btn btn-circle btn-outline btn-sm border-base-300 font-sans"
                aria-label="How it works"
                x-on:click="sheetOpen = true"
            >
                ?
            </button>
        @endunless
    </div>

    @if ($this->showsCard)
        <section class="card card-border border-base-300 mt-6 font-sans" aria-labelledby="how-it-works-title">
            <div class="card-body gap-3 p-4">
                <div class="flex items-center justify-between gap-4">
                    <h2 id="how-it-works-title" class="card-title text-base">How it works</h2>
                    <button
                        type="button"
                        class="btn btn-circle btn-ghost btn-sm"
                        aria-label="Close How it works"
                        wire:click="dismiss"
                        wire:loading.attr="disabled"
                    >
                        ✕
                    </button>
                </div>
                <x-memories.how-it-works-lines />
            </div>
        </section>
    @else
        <div
            class="modal modal-bottom"
            x-bind:class="sheetOpen && 'modal-open'"
            x-on:keydown.escape.window="sheetOpen = false"
        >
            <div class="modal-box font-sans" role="dialog" aria-modal="true" aria-labelledby="how-it-works-sheet-title">
                <h3 id="how-it-works-sheet-title" class="font-serif text-2xl">How it works</h3>
                <x-memories.how-it-works-lines class="mt-4" />
                <button type="button" class="btn btn-ghost btn-block mt-6" x-on:click="sheetOpen = false">Close</button>
            </div>
            <div class="modal-backdrop" x-on:click="sheetOpen = false"></div>
        </div>
    @endif
</div>
