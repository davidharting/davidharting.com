/**
 * The practice page's swipe stack. Tap the card to reveal the definition,
 * then swipe right for Got it or left for Missed it, or use the buttons or
 * the arrow keys. The card flies off and the next one shows straight away;
 * the grade is saved in the background. A grade that fails to save puts the
 * card back at the bottom of the stack.
 *
 * When a round runs out, the next one is fetched once every grade has
 * saved, so a big backlog comes in batches and the caught-up screen only
 * shows when nothing is due.
 */
const SWIPE_THRESHOLD_PIXELS = 100;
const FLY_OUT_MILLISECONDS = 200;
const NOTICE_LASTS_MILLISECONDS = 4000;

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

            cards: firstRound.cards,
            nextDueAt: firstRound.nextDueAt,
            index: 0,
            revealed: false,
            results: [],
            dealing: false,
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

            nextDue() {
                return this.nextDueAt ? fromNow(this.nextDueAt) : null;
            },

            gotCount() {
                return this.results.filter((result) => result.gotIt).length;
            },

            missedWords() {
                return this.results
                    .filter((result) => !result.gotIt)
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
                    this.grade(this.dx > 0);
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

            grade(gotIt) {
                if (!this.revealed || this.flyingOut || this.done()) {
                    return;
                }
                const card = this.current();
                const result = { card, gotIt };
                this.flyingOut = gotIt ? 1 : -1;
                this.results.push(result);
                saving.push(
                    // Livewire rejects when the network fails but resolves
                    // other failures with null, so anything but true is lost.
                    wire.grade(card.id, gotIt).then(
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
                        this.deal(() => wire.dueRound());
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

            practiseAhead() {
                this.results = [];
                this.deal(async () => ({
                    cards: await wire.practiseAhead(),
                    nextDueAt: this.nextDueAt,
                }));
            },

            /**
             * Replace the stack with a new round, once every grade has saved so
             * the round reflects them.
             */
            async deal(fetchRound) {
                this.dealing = true;
                try {
                    await Promise.allSettled(saving);
                    saving = [];
                    // A failed grade may have put a card back while we waited.
                    if (!this.done()) {
                        return;
                    }
                    const round = await fetchRound().catch(() => null);
                    if (!round) {
                        this.showNotice(
                            "Couldn't deal more cards. Try again in a moment.",
                        );
                        return;
                    }
                    this.cards = round.cards;
                    this.nextDueAt = round.nextDueAt;
                    this.index = 0;
                } finally {
                    this.dealing = false;
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
                } else if (event.key === "ArrowRight") {
                    this.grade(true);
                } else if (event.key === "ArrowLeft") {
                    this.grade(false);
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
