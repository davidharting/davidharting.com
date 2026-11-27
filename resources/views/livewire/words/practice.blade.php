{{--
    A full-screen swipe stack. Tap the card to reveal the definition, then
    grade it with a button: Missed it, Kind of got it, or Got it. On a touch
    screen, swiping left or right is a shortcut for Missed it and Got it; the
    swipe hints sit where the thumb starts, pointing the way it goes. With a
    mouse (a fine pointer) there is nothing to swipe, so the hints give way
    to each button's key. The button row always takes its space, so the card
    is the same size before and after the reveal. On wide screens the stack
    is capped, so the card stays card sized instead of filling the window.
--}}
<div
    x-data="wordPractice(@js($firstRound))"
    x-on:keydown.window="handleKey($event)"
    class="bg-base-200 flex h-dvh flex-col p-4 font-sans"
>
    <header class="flex items-center justify-between">
        <a href="{{ route("words.index") }}" class="btn btn-ghost btn-sm">✕ Done</a>
        <div class="flex items-center gap-2" x-show="! done()">
            <span class="badge badge-warning" x-show="current() && ! current().due">Practising ahead</span>
            <span class="badge badge-neutral" x-text="`${remaining()} left`"></span>
        </div>
    </header>

    <p class="sr-only" aria-live="polite" x-text="announcement()"></p>

    <template x-if="notice">
        <div class="toast toast-top toast-center z-10">
            <div class="alert alert-warning" role="alert" x-text="notice"></div>
        </div>
    </template>

    <template x-if="done() && waiting">
        <div class="flex flex-1 items-center justify-center">
            <span class="loading loading-dots loading-lg" aria-label="Saving your grades"></span>
        </div>
    </template>

    <template x-if="done() && ! waiting">
        <div class="mx-auto flex max-w-md flex-1 flex-col items-center justify-center gap-4 text-center">
            @if ($hasWords)
                <div class="text-5xl" aria-hidden="true" x-text="moreDue() ? '👏' : '🌿'"></div>
                <h1 class="font-serif text-3xl" x-text="moreDue() ? 'Round done' : 'All caught up'"></h1>

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

                <p class="text-base-content/70" x-show="moreDue()" x-text="`${upNext?.dueCount} more due.`"></p>
                <p
                    class="text-base-content/70"
                    x-show="! moreDue() && nextDue()"
                    x-text="`The next word is due ${nextDue()}. Anything more is practising ahead.`"
                ></p>

                <div class="flex flex-col items-center gap-2">
                    <button
                        type="button"
                        class="btn btn-primary"
                        x-on:click="practiseMore()"
                        x-text="moreDue() ? 'Practise 5 more' : 'Practise 5 ahead'"
                    ></button>
                    <a class="link text-sm" href="{{ route("words.index") }}">Back to Words</a>
                </div>
            @else
                <h1 class="font-serif text-3xl">No words yet</h1>
                <p class="text-base-content/70">Add a word and it will show up here to practise.</p>
                <a class="btn btn-outline" href="{{ route("filament.admin.resources.words.create") }}">Add a word</a>
            @endif
        </div>
    </template>

    <template x-if="! done()">
        <div class="mx-auto flex w-full max-w-xl flex-1 flex-col pt-4 md:justify-center">
            <div class="relative flex-1 md:max-h-[32rem]">
                {{-- The next cards peek out from behind the top one. --}}
                <template x-if="cards[index + 2]">
                    <div class="card bg-base-100 absolute inset-x-6 top-6 bottom-0 shadow-sm"></div>
                </template>
                <template x-if="cards[index + 1]">
                    <div class="card bg-base-100 absolute inset-x-3 top-3 bottom-3 shadow"></div>
                </template>

                {{--
                    Keyed by card, so each card is a fresh element rather than
                    the last one sliding back. touch-pan-y leaves vertical
                    scrolling to the browser for long definitions; a scroll
                    cancels the drag.
                --}}
                <template x-for="card in [current()]" x-bind:key="card.id">
                    <div
                        class="card bg-base-100 absolute inset-x-0 top-0 bottom-6 touch-pan-y overflow-hidden shadow-lg select-none"
                        x-bind:style="cardStyle()"
                        x-on:pointerdown="startDrag($event)"
                        x-on:pointermove.window="drag($event)"
                        x-on:pointerup.window="endDrag($event)"
                        x-on:pointercancel.window="cancelDrag($event)"
                        x-on:click="reveal()"
                    >
                        <div class="card-body min-h-0 items-center overflow-y-auto text-center">
                            <h1 class="font-serif text-5xl break-words" x-text="card.word"></h1>
                            <p class="text-base-content/50 mt-6 text-sm" x-show="! revealed">
                                Say the definition, then tap
                            </p>
                            <p
                                class="mt-6 font-serif text-2xl leading-snug"
                                x-show="revealed"
                                x-text="card.definition"
                            ></p>
                        </div>

                        {{-- Each hint lights up once the drag is far enough to grade that way. --}}
                        <div
                            class="flex justify-between px-4 pb-4 pointer-fine:hidden"
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
                </template>
            </div>

            {{-- Always the same height, so the card never changes size. --}}
            <div class="flex h-20 items-center justify-center">
                <template x-if="! revealed">
                    <button type="button" class="btn btn-lg btn-neutral" x-on:click="reveal()">
                        Show definition
                        <kbd class="kbd kbd-sm hidden pointer-fine:inline-flex" aria-hidden="true">Space</kbd>
                    </button>
                </template>
                <template x-if="revealed">
                    <div class="grid w-full grid-cols-3 gap-2">
                        <template x-for="choice in grades" x-bind:key="choice.rating">
                            <button
                                type="button"
                                class="btn h-14 leading-tight"
                                x-bind:class="choice.buttonClass"
                                x-on:click="grade(choice.rating)"
                            >
                                <span x-text="choice.label"></span>
                                <kbd
                                    class="kbd kbd-sm hidden pointer-fine:inline-flex"
                                    aria-hidden="true"
                                    x-text="choice.keys[0]"
                                ></kbd>
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
