{{-- PROTOTYPE variant C: inside the site, dictionary-page style. Type what you think it means, then compare. --}}
<div
    x-data="{
        ...practiceSession(@js($cards)),
        attempt: '',
        afterGrade() {
            this.attempt = ''
            this.$nextTick(() => this.$refs.attempt?.focus())
        },
    }"
    class="mx-auto max-w-xl"
>
    <div class="flex items-baseline justify-between">
        <h1 class="font-serif text-4xl">Practice</h1>
        <span class="text-base-content/70 text-sm" x-text="cards.length - index + ' due today'"></span>
    </div>

    <template x-if="done()">
        <div>@include('prototype.practice.caught-up')</div>
    </template>

    <template x-if="! done()">
        <div class="mt-8">
            <p class="font-serif text-5xl" x-text="current().word"></p>

            <fieldset class="fieldset mt-6">
                <label class="fieldset-label" for="attempt">What does it mean? Type it, or just think it.</label>
                <textarea
                    id="attempt"
                    x-ref="attempt"
                    x-model="attempt"
                    class="textarea w-full text-base"
                    rows="3"
                    :readonly="revealed"
                    placeholder="In your own words…"
                ></textarea>
            </fieldset>

            <template x-if="! revealed">
                <button type="button" class="btn btn-primary btn-block mt-4" x-on:click="reveal()">Check</button>
            </template>

            <template x-if="revealed">
                <div class="mt-6">
                    <div class="border-primary border-l-4 pl-4">
                        <p class="text-base-content/60 text-xs tracking-wide uppercase">Definition</p>
                        <p class="mt-1 font-serif text-2xl leading-snug" x-text="current().short"></p>
                        <div class="prose prose-sm mt-4" x-html="current().full"></div>
                    </div>

                    <p class="mt-6 text-sm">How did you do?</p>
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <button type="button" class="btn btn-error btn-outline" x-on:click="grade(false)">
                            Missed it
                        </button>
                        <button type="button" class="btn btn-success" x-on:click="grade(true)">Got it</button>
                    </div>
                </div>
            </template>

            <template x-if="results.length > 0">
                <div class="mt-10">
                    <p class="text-base-content/60 text-xs tracking-wide uppercase">This session</p>
                    <ul class="mt-2 flex flex-wrap gap-2">
                        <template x-for="result in results">
                            <li
                                class="badge"
                                :class="result.gotIt ? 'badge-success badge-soft' : 'badge-error badge-soft'"
                                x-text="result.word"
                            ></li>
                        </template>
                    </ul>
                </div>
            </template>
        </div>
    </template>
</div>
