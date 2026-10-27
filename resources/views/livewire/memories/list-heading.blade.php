<div x-data>
    <div class="flex items-center gap-3">
        <x-type.page-title class="text-5xl">Memories</x-type.page-title>

        @unless ($this->showsCard)
            <button
                type="button"
                wire:key="how-it-works-open"
                class="btn btn-circle btn-outline border-base-300 font-sans"
                aria-label="How it works"
                aria-haspopup="dialog"
                x-on:click="$refs.howItWorks.showModal()"
            >
                ?
            </button>
        @endunless
    </div>

    @if ($this->showsCard)
        <section
            wire:key="how-it-works-card"
            class="card card-border border-base-300 mt-6 font-sans"
            aria-labelledby="how-it-works-title"
        >
            <div class="card-body gap-3 p-4">
                <div class="flex items-center justify-between gap-4">
                    <h2 id="how-it-works-title" class="card-title text-base">How it works</h2>
                    <button
                        type="button"
                        class="btn btn-circle btn-ghost -mr-2"
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
        <dialog
            wire:key="how-it-works-sheet"
            x-ref="howItWorks"
            class="modal modal-bottom"
            aria-labelledby="how-it-works-sheet-title"
        >
            <div class="modal-box font-sans">
                <h2 id="how-it-works-sheet-title" class="font-serif text-2xl">How it works</h2>
                <x-memories.how-it-works-lines class="mt-4" />
                <form method="dialog" class="mt-6">
                    <button class="btn btn-ghost btn-block">Close</button>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button tabindex="-1">Close</button>
            </form>
        </dialog>
    @endif
</div>
