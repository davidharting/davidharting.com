{{-- PROTOTYPE variant D: reverse prompt. Show the definition, recall the word, with a first-letter hint. --}}
<div
    x-data="{
        ...practiceSession(@js($cards)),
        hinted: false,
        afterGrade() {
            this.hinted = false
        },
        hint() {
            const word = this.current().word
            return word[0] + ' ' + '_ '.repeat(word.length - 1)
        },
    }"
    class="mx-auto max-w-xl"
>
    <div class="flex justify-center gap-2" aria-label="Progress">
        <template x-for="(card, i) in cards">
            <span
                class="size-2.5 rounded-full"
                :class="i < index ? 'bg-primary' : i === index ? 'bg-base-content' : 'bg-base-300'"
            ></span>
        </template>
    </div>

    <template x-if="done()">
        <div>@include('prototype.practice.caught-up')</div>
    </template>

    <template x-if="! done()">
        <div class="mt-8 flex flex-col gap-6">
            <p class="text-base-content/60 text-xs tracking-wide uppercase">Which word means…</p>
            <p class="font-serif text-3xl leading-snug" x-text="current().short"></p>

            <template x-if="! revealed">
                <div class="flex flex-col gap-3">
                    <p class="font-mono text-2xl tracking-widest" x-show="hinted" x-text="hint()"></p>
                    <button type="button" class="btn btn-primary btn-block" x-on:click="reveal()">Show the word</button>
                    <button type="button" class="btn btn-ghost btn-sm" x-show="! hinted" x-on:click="hinted = true">
                        Give me the first letter
                    </button>
                </div>
            </template>

            <template x-if="revealed">
                <div class="flex flex-col gap-4">
                    <p class="font-serif text-5xl" x-text="current().word"></p>
                    <div class="prose prose-sm" x-html="current().full"></div>
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" class="btn btn-error btn-outline" x-on:click="grade(false)">
                            Missed it
                        </button>
                        <button type="button" class="btn btn-success" x-on:click="grade(true)">Got it</button>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>
