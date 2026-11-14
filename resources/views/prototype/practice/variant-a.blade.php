{{-- PROTOTYPE variant A: full-screen flip card. Tap the card to flip it, grade with two thumb-zone buttons. --}}
<div
    x-data="practiceSession(@js($cards))"
    class="flex min-h-[calc(100dvh-5rem)] flex-col px-4 pt-4"
    x-on:keydown.space.window.prevent="revealed ? null : reveal()"
>
    <header class="flex items-center gap-3">
        <a href="{{ route("home") }}" class="btn btn-ghost btn-sm btn-circle" aria-label="Stop practising">✕</a>
        <progress class="progress progress-primary flex-1" :value="index" :max="cards.length"></progress>
        <span class="text-sm tabular-nums" x-text="Math.min(index + 1, cards.length) + ' / ' + cards.length"></span>
    </header>

    <template x-if="done()">
        <div>@include('prototype.practice.caught-up')</div>
    </template>

    <template x-if="! done()">
        <div class="flex flex-1 flex-col gap-4 py-6">
            <button
                type="button"
                class="relative flex-1 [perspective:1200px]"
                x-on:click="revealed = ! revealed"
                aria-label="Flip the card"
            >
                <div
                    class="relative h-full min-h-80 w-full transition-transform duration-500 [transform-style:preserve-3d]"
                    :class="revealed && '[transform:rotateY(180deg)]'"
                >
                    <div class="card bg-base-200 absolute inset-0 items-center justify-center shadow-md [backface-visibility:hidden]">
                        <p class="font-serif text-5xl" x-text="current().word"></p>
                        <p class="text-base-content/50 mt-6 text-sm">Tap to see the definition</p>
                    </div>
                    <div class="card bg-base-100 border-base-300 absolute inset-0 [transform:rotateY(180deg)] overflow-y-auto border p-6 text-left shadow-md [backface-visibility:hidden]">
                        <p class="text-base-content/60 font-serif text-xl" x-text="current().word"></p>
                        <p class="mt-3 font-serif text-2xl leading-snug" x-text="current().short"></p>
                        <details class="mt-6" x-on:click.stop>
                            <summary class="link text-sm">Full entry</summary>
                            <div class="prose prose-sm mt-2" x-html="current().full"></div>
                        </details>
                    </div>
                </div>
            </button>

            <div class="grid grid-cols-2 gap-3">
                <template x-if="! revealed">
                    <button type="button" class="btn btn-primary btn-lg col-span-2" x-on:click="reveal()">
                        Show definition
                    </button>
                </template>
                <template x-if="revealed">
                    <button type="button" class="btn btn-error btn-outline btn-lg" x-on:click="grade(false)">
                        Missed it
                    </button>
                </template>
                <template x-if="revealed">
                    <button type="button" class="btn btn-success btn-lg" x-on:click="grade(true)">Got it</button>
                </template>
            </div>
        </div>
    </template>
</div>
