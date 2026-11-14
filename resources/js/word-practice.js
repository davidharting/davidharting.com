/**
 * The practice page's swipe stack. Tap the card to reveal the definition,
 * then swipe right for Got it or left for Missed it, or use the buttons or
 * the arrow keys. The card flies off and the next one shows straight away;
 * the grade is saved in the background.
 */
const SWIPE_THRESHOLD_PX = 100;
const FLY_OUT_MS = 200;

document.addEventListener("alpine:init", () => {
    window.Alpine.data("wordPractice", (firstRound, nextDue) => ({
        cards: firstRound,
        index: 0,
        revealed: false,
        results: [],
        nextDue,
        dealing: false,

        // Drag state: how far the card has moved, where the pointer went
        // down, and which way the card is flying off (1 right, -1 left).
        dx: 0,
        startX: null,
        flyingOut: 0,

        current() {
            return this.cards[this.index] ?? null;
        },

        done() {
            return this.index >= this.cards.length;
        },

        left() {
            return this.cards.length - this.index;
        },

        gotCount() {
            return this.results.filter((result) => result.gotIt).length;
        },

        missedWords() {
            return this.results
                .filter((result) => !result.gotIt)
                .map((result) => result.word);
        },

        reveal() {
            if (!this.done()) {
                this.revealed = true;
            }
        },

        down(event) {
            if (this.revealed && !this.flyingOut) {
                this.startX = event.clientX;
            }
        },

        move(event) {
            if (this.startX !== null) {
                this.dx = event.clientX - this.startX;
            }
        },

        up() {
            if (this.startX === null) {
                return;
            }
            this.startX = null;
            if (Math.abs(this.dx) > SWIPE_THRESHOLD_PX) {
                this.grade(this.dx > 0);
            } else {
                this.dx = 0;
            }
        },

        cancel() {
            this.startX = null;
            this.dx = 0;
        },

        grade(gotIt) {
            if (!this.revealed || this.flyingOut || this.done()) {
                return;
            }
            const card = this.current();
            this.flyingOut = gotIt ? 1 : -1;
            this.$wire.grade(card.id, gotIt).then((nextDue) => {
                this.nextDue = nextDue;
            });

            setTimeout(() => {
                this.results.push({ word: card.word, gotIt });
                this.flyingOut = 0;
                this.dx = 0;
                this.revealed = false;
                this.index++;
            }, FLY_OUT_MS);
        },

        async practiseAhead() {
            this.dealing = true;
            try {
                this.cards = await this.$wire.practiseAhead();
                this.index = 0;
                this.results = [];
                this.revealed = false;
            } finally {
                this.dealing = false;
            }
        },

        key(event) {
            if (event.key === " " || event.key === "Enter") {
                if (!this.revealed) {
                    event.preventDefault();
                    this.reveal();
                }
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
                this.startX === null
                    ? `transform ${FLY_OUT_MS}ms ease-out`
                    : "none";
            return `transform: translateX(${x}px) rotate(${x / 20}deg); transition: ${transition}`;
        },
    }));
});
