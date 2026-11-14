{{--
    A full-screen swipe stack. Tap the card to reveal the definition, then
    swipe right for Got it or left for Missed it. The swipe hints sit where
    the thumb starts, pointing the way it goes. The button row always takes
    its space, so the card is the same size before and after the reveal.
--}}
<div
    x-data="wordPractice(@js($this->dueCards), @js($this->nextDue))"
    x-on:keydown.window="key($event)"
    class="bg-base-200 flex h-dvh flex-col px-4 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))] font-sans"
>
    <header class="flex items-center justify-between">
        <a href="{{ route("home") }}" class="btn btn-ghost btn-sm">✕ Done</a>
        <span class="badge badge-neutral" x-show="! done()" x-text="`${left()} left`"></span>
    </header>

    <template x-if="done()">
        <div class="mx-auto flex max-w-md flex-1 flex-col items-center justify-center gap-4 text-center">
            @if ($this->hasWords)
                <div class="text-5xl" aria-hidden="true">🌿</div>
                <h1 class="font-serif text-3xl">All caught up</h1>

                <template x-if="results.length > 0">
                    <div class="flex flex-col gap-2">
                        <p x-text="`You got ${gotCount()} of ${results.length}.`"></p>
                        <template x-if="missedWords().length > 0">
                            <p
                                class="text-base-content/70"
                                x-text="`You'll see ${missedWords().join(', ')} again soon.`"
                            ></p>
                        </template>
                    </div>
                </template>

                <p class="text-base-content/70" x-show="nextDue" x-text="`The next word is due ${nextDue}.`"></p>

                <div class="flex flex-col items-center gap-2">
                    <button
                        type="button"
                        class="btn btn-outline"
                        x-on:click="practiseAhead()"
                        x-bind:disabled="dealing"
                    >
                        Practise ahead anyway
                    </button>
                    <a class="link text-sm" href="{{ route("home") }}">Back to the site</a>
                </div>
            @else
                <h1 class="font-serif text-3xl">No words yet</h1>
                <p class="text-base-content/70">Add a word and it will show up here to practise.</p>
                <a class="btn btn-outline" href="{{ route("filament.admin.resources.words.create") }}">Add a word</a>
            @endif
        </div>
    </template>

    <template x-if="! done()">
        <div class="flex flex-1 flex-col pt-4">
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
                    x-bind:style="cardStyle()"
                    x-on:pointerdown="down($event)"
                    x-on:pointermove.window="move($event)"
                    x-on:pointerup.window="up()"
                    x-on:pointercancel.window="cancel()"
                    x-on:click="reveal()"
                >
                    <div class="card-body items-center justify-center overflow-y-auto text-center">
                        <h1 class="font-serif text-5xl break-words" x-text="current().word"></h1>
                        <p class="text-base-content/50 mt-6 text-sm" x-show="! revealed">Say the definition, then tap</p>
                        <p
                            class="mt-6 font-serif text-2xl leading-snug"
                            x-show="revealed"
                            x-text="current().definition"
                            aria-live="polite"
                        ></p>
                    </div>

                    {{-- Each hint lights up once the drag is far enough to grade that way. --}}
                    <div
                        class="flex justify-between px-4 pb-4"
                        x-bind:class="revealed || 'invisible'"
                        aria-hidden="true"
                    >
                        <span class="badge badge-lg" x-bind:class="dx > 40 ? 'badge-success' : 'badge-ghost'">
                            Got it →
                        </span>
                        <span class="badge badge-lg" x-bind:class="dx < -40 ? 'badge-error' : 'badge-ghost'">
                            ← Missed it
                        </span>
                    </div>
                </div>
            </div>

            {{-- Always the same height, so the card never changes size. --}}
            <div class="flex h-20 items-center justify-center gap-8">
                <template x-if="! revealed">
                    <button type="button" class="btn btn-lg btn-neutral" x-on:click="reveal()">Show definition</button>
                </template>
                <template x-if="revealed">
                    <div class="flex gap-8">
                        <button
                            type="button"
                            class="btn btn-circle btn-lg btn-error btn-outline"
                            x-on:click="grade(false)"
                            aria-label="Missed it"
                        >
                            ✕
                        </button>
                        <button
                            type="button"
                            class="btn btn-circle btn-lg btn-success"
                            x-on:click="grade(true)"
                            aria-label="Got it"
                        >
                            ✓
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
