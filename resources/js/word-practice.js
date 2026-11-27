/**
 * The practice page's swipe stack. Tap the card to reveal the definition,
 * then grade it with the buttons or their keys: Missed it, Kind of got it,
 * or Got it. Swiping left or right is a shortcut for Missed it and Got it. The card flies off and the next one shows straight away;
 * the grade is saved in the background. A grade that fails to save puts the
 * card back at the bottom of the stack.
 *
 * A round is five cards (Practice::ROUND_SIZE). When it runs out, the
 * page shows the score and, once every grade has saved, offers the next
 * round if more words are due, or says you're caught up.
 */
const SWIPE_THRESHOLD_PIXELS = 100;
const FLY_OUT_MILLISECONDS = 200;
const NOTICE_LASTS_MILLISECONDS = 4000;

/**
 * App\Fsrs\Rating values, as the server takes them.
 */
const AGAIN = 1;
const HARD = 2;
const GOOD = 3;

/**
 * The grades offered once the definition shows, left to right, with the
 * keys that give them and the way the card flies off. Kind of got it counts
 * as remembered. Swipes stay left for Missed it and right for Got it, since
 * vertical drags scroll a long definition.
 */
const GRADES = [
    {
        rating: AGAIN,
        label: "Missed it",
        keys: ["ArrowLeft", "1"],
        keyHint: "←",
        flyOut: -1,
        buttonClass: "btn-error btn-outline",
    },
    {
        rating: HARD,
        label: "Kind of got it",
        keys: ["ArrowDown", "2"],
        keyHint: "↓",
        flyOut: 1,
        buttonClass: "btn-warning btn-outline",
    },
    {
        rating: GOOD,
        label: "Got it",
        keys: ["ArrowRight", "3"],
        keyHint: "→",
        flyOut: 1,
        buttonClass: "btn-success",
    },
];

const RELATIVE_UNITS = [
    ["day", 86400],
    ["hour", 3600],
    ["minute", 60],
    ["second", 1],
];

/**
 * "in 10 minutes", "tomorrow", for an ISO 8601 timestamp.
 */
function fromNow(timestamp) {
    const seconds = (new Date(timestamp) - Date.now()) / 1000;
    const [unit, size] =
        RELATIVE_UNITS.find(([, size]) => Math.abs(seconds) >= size) ??
        RELATIVE_UNITS.at(-1);
    return new Intl.RelativeTimeFormat(undefined, { numeric: "auto" }).format(
        Math.round(seconds / size),
        unit,
    );
}

document.addEventListener("alpine:init", () => {
    window.Alpine.data("wordPractice", (firstRound) => {
        // Grades still saving. Kept out of Alpine's reactive state, whose
        // proxies would hide that these are promises from Promise.allSettled.
        let saving = [];
        // The component's $wire, taken once at init: Alpine resolves magics
        // from the element a handler ran on, which may be gone by the time
        // a round ends.
        let wire = null;

        return {
            init() {
                wire = this.$wire;
            },

            grades: GRADES,
            cards: firstRound.cards,
            // The round after this one, fetched when this one ends. With
            // nothing due, the first round is empty and is its own "next".
            upNext: firstRound.cards.length === 0 ? firstRound : null,
            nextDueAt: firstRound.nextDueAt,
            index: 0,
            revealed: false,
            results: [],
            waiting: false,
            notice: null,
            noticeTimer: null,

            // Drag state: how far the card has moved, which pointer is dragging
            // it from where, and which way it is flying off (1 right, -1 left).
            dx: 0,
            pointerId: null,
            startX: null,
            flyingOut: 0,

            current() {
                return this.cards[this.index] ?? null;
            },

            done() {
                return this.index >= this.cards.length;
            },

            remaining() {
                return this.cards.length - this.index;
            },

            /** What a screen reader hears: the word, then its definition. */
            announcement() {
                const card = this.current();
                if (!card) {
                    return "";
                }
                return this.revealed ? card.definition : card.word;
            },

            moreDue() {
                return this.upNext?.cards.length > 0;
            },

            nextDue() {
                return this.nextDueAt ? fromNow(this.nextDueAt) : null;
            },

            gotCount() {
                return this.results.filter((result) => result.rating !== AGAIN)
                    .length;
            },

            missedWords() {
                return this.results
                    .filter((result) => result.rating === AGAIN)
                    .map((result) => result.card.word);
            },

            reveal() {
                if (!this.done()) {
                    this.revealed = true;
                }
            },

            startDrag(event) {
                if (!event.isPrimary || !this.revealed || this.flyingOut) {
                    return;
                }
                this.pointerId = event.pointerId;
                this.startX = event.clientX;
            },

            drag(event) {
                if (event.pointerId === this.pointerId) {
                    this.dx = event.clientX - this.startX;
                }
            },

            endDrag(event) {
                if (event.pointerId !== this.pointerId) {
                    return;
                }
                this.pointerId = null;
                if (Math.abs(this.dx) > SWIPE_THRESHOLD_PIXELS) {
                    this.grade(this.dx > 0 ? GOOD : AGAIN);
                } else {
                    this.dx = 0;
                }
            },

            cancelDrag(event) {
                if (event.pointerId === this.pointerId) {
                    this.pointerId = null;
                    this.dx = 0;
                }
            },

            grade(rating) {
                if (!this.revealed || this.flyingOut || this.done()) {
                    return;
                }
                const card = this.current();
                const result = { card, rating };
                this.flyingOut = GRADES.find(
                    (choice) => choice.rating === rating,
                ).flyOut;
                this.results.push(result);
                saving.push(
                    // Livewire rejects when the network fails but resolves
                    // other failures with null, so anything but true is lost.
                    wire.grade(card.id, rating).then(
                        (saved) => saved === true || this.unsaved(result),
                        () => this.unsaved(result),
                    ),
                );

                setTimeout(() => {
                    this.flyingOut = 0;
                    this.dx = 0;
                    this.revealed = false;
                    this.index++;
                    if (this.done()) {
                        this.finishRound();
                    }
                }, FLY_OUT_MILLISECONDS);
            },

            /** Put a card whose grade didn't save back in the stack. */
            unsaved(result) {
                this.results.splice(this.results.indexOf(result), 1);
                this.cards.push(result.card);
                this.showNotice(
                    `Couldn't save “${result.card.word}”, so it's back in the stack.`,
                );
            },

            /**
             * Once every grade has saved, see what is due next, for the
             * screen between rounds. A failed grade may have put a card back
             * while we waited, in which case the round isn't over.
             */
            async finishRound() {
                await this.afterSaving(async () => {
                    const round = await wire.dueRound().catch(() => null);
                    if (!round) {
                        this.showNotice(
                            "Couldn't check what's due next. Try again in a moment.",
                        );
                        return;
                    }
                    this.upNext = round;
                    this.nextDueAt = round.nextDueAt;
                });
            },

            /**
             * Another round of five: the due cards already fetched, or once
             * nothing is due, whichever cards come up soonest.
             */
            async practiseMore() {
                if (this.moreDue()) {
                    this.startRound(this.upNext.cards);
                    return;
                }
                await this.afterSaving(async () => {
                    const cards = await wire.practiseAhead().catch(() => null);
                    if (!cards) {
                        this.showNotice(
                            "Couldn't draw more cards. Try again in a moment.",
                        );
                        return;
                    }
                    this.startRound(cards);
                });
            },

            startRound(cards) {
                this.cards = cards;
                this.index = 0;
                this.results = [];
                this.upNext = null;
            },

            /** Run `then` once every grade has saved, if the round is still over. */
            async afterSaving(then) {
                this.waiting = true;
                try {
                    await Promise.allSettled(saving);
                    saving = [];
                    if (this.done()) {
                        await then();
                    }
                } finally {
                    this.waiting = false;
                }
            },

            showNotice(message) {
                this.notice = message;
                clearTimeout(this.noticeTimer);
                this.noticeTimer = setTimeout(() => {
                    this.notice = null;
                }, NOTICE_LASTS_MILLISECONDS);
            },

            handleKey(event) {
                if (this.done() || event.target.closest("a, button, input")) {
                    return;
                }
                if (
                    (event.key === " " || event.key === "Enter") &&
                    !this.revealed
                ) {
                    event.preventDefault();
                    this.reveal();
                    return;
                }
                const choice = GRADES.find(({ keys }) =>
                    keys.includes(event.key),
                );
                if (choice) {
                    event.preventDefault();
                    this.grade(choice.rating);
                }
            },

            cardStyle() {
                const x = this.flyingOut
                    ? this.flyingOut * window.innerWidth
                    : this.dx;
                const transition =
                    this.pointerId === null
                        ? `transform ${FLY_OUT_MILLISECONDS}ms ease-out`
                        : "none";
                return `transform: translateX(${x}px) rotate(${x / 20}deg); transition: ${transition}`;
            },
        };
    });
});
