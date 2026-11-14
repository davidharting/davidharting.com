{{-- PROTOTYPE variant B: full-screen swipe stack. Tap to reveal, then swipe right for Got it, left for Missed it. --}}
<div
    x-data="{
        ...practiceSession(@js($cards)),
        dx: 0,
        startX: null,
        flyingOut: 0,
        down(event) {
            if (! this.revealed) return
            this.startX = event.clientX
        },
        move(event) {
            if (this.startX === null) return
            this.dx = event.clientX - this.startX
        },
        up() {
            if (this.startX === null) return
            this.startX = null
            if (Math.abs(this.dx) > 100) {
                this.swipe(this.dx > 0)
            } else {
                this.dx = 0
            }
        },
        swipe(gotIt) {
            this.flyingOut = gotIt ? 1 : -1
            setTimeout(() => {
                this.flyingOut = 0
                this.dx = 0
                this.grade(gotIt)
            }, 200)
        },
        cardStyle() {
            const x = this.flyingOut ? this.flyingOut * 600 : this.dx
            return `transform: translateX(${x}px) rotate(${x / 20}deg); transition: ${this.startX === null ? 'transform 200ms' : 'none'}`
        },
    }"
    class="bg-base-200 flex min-h-[calc(100dvh-5rem)] flex-col px-4 pt-4"
>
    <header class="flex items-center justify-between">
        <a href="{{ route("home") }}" class="btn btn-ghost btn-sm" aria-label="Stop practising">✕ Done</a>
        <span class="badge badge-neutral" x-text="cards.length - index + ' left'"></span>
    </header>

    <template x-if="done()">
        <div>@include('prototype.practice.caught-up')</div>
    </template>

    <template x-if="! done()">
        <div class="flex flex-1 flex-col py-6">
            <div class="relative flex-1">
                {{-- The next cards peek out from behind the top one. --}}
                <template x-if="cards[index + 2]">
                    <div class="card bg-base-100 absolute inset-x-6 top-6 bottom-0 shadow-sm"></div>
                </template>
                <template x-if="cards[index + 1]">
                    <div class="card bg-base-100 absolute inset-x-3 top-3 bottom-3 shadow"></div>
                </template>

                <div
                    class="card bg-base-100 absolute inset-x-0 top-0 bottom-6 touch-none overflow-hidden shadow-lg select-none"
                    :style="cardStyle()"
                    x-on:pointerdown="down($event)"
                    x-on:pointermove.window="move($event)"
                    x-on:pointerup.window="up()"
                    x-on:click="revealed || reveal()"
                >
                    <div
                        class="badge badge-success badge-lg absolute top-6 left-6 rotate-[-12deg]"
                        :style="`opacity: ${Math.max(0, dx / 100)}`"
                    >
                        Got it
                    </div>
                    <div
                        class="badge badge-error badge-lg absolute top-6 right-6 rotate-[12deg]"
                        :style="`opacity: ${Math.max(0, -dx / 100)}`"
                    >
                        Missed it
                    </div>

                    <div class="card-body items-center justify-center text-center">
                        <p class="font-serif text-5xl" x-text="current().word"></p>
                        <template x-if="! revealed">
                            <p class="text-base-content/50 mt-6 text-sm">Say the definition, then tap</p>
                        </template>
                        <template x-if="revealed">
                            <div>
                                <p class="mt-6 font-serif text-2xl leading-snug" x-text="current().short"></p>
                                <div class="text-base-content/50 mt-8 flex w-full justify-between text-sm">
                                    <span>Got it →</span>
                                    <span>← Missed it</span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Always takes up its space, so the card stays one size when the buttons appear. --}}
            <div class="mt-4 flex justify-center gap-8" :class="revealed || 'invisible'">
                <button
                    type="button"
                    class="btn btn-circle btn-lg btn-error btn-outline"
                    x-on:click="swipe(false)"
                    aria-label="Missed it"
                >
                    ✕
                </button>
                <button
                    type="button"
                    class="btn btn-circle btn-lg btn-success"
                    x-on:click="swipe(true)"
                    aria-label="Got it"
                >
                    ✓
                </button>
            </div>
        </div>
    </template>
</div>
