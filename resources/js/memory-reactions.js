/**
 * The reaction row's picker and toast. + opens a few quick picks. A page
 * can't open the phone's emoji keyboard, so More… focuses a hidden input and
 * the first emoji typed into it is sent, after which the keyboard closes.
 * Anything else typed is dropped. The reaction you just added pops in, but
 * not the ones already there when the page loads or added by someone else.
 *
 * The emoji test here is looser than the server's, in
 * App\Support\Emoji, which has the final say.
 */
const EMOJI = /[\p{Extended_Pictographic}\p{Regional_Indicator}\u{20E3}]/u;
const POP_LASTS_MILLISECONDS = 800;

document.addEventListener("alpine:init", () => {
    window.Alpine.data("memoryReactions", () => ({
        open: false,
        hint: null,
        notice: null,
        noticeTimer: null,
        justAdded: null,
        segmenter: new Intl.Segmenter(),

        add(emoji) {
            this.justAdded = emoji;
            setTimeout(() => {
                if (this.justAdded === emoji) {
                    this.justAdded = null;
                }
            }, POP_LASTS_MILLISECONDS);
            this.$wire.add(emoji);
        },

        quickPick(emoji) {
            this.open = false;
            this.add(emoji);
        },

        pick() {
            this.$refs.picker.focus();

            if (
                !window.matchMedia("(hover: hover) and (pointer: fine)").matches
            ) {
                this.hint = "Tap 😀 or 🌐 on your keyboard to find the emoji.";
            } else if (/Mac/.test(navigator.platform)) {
                this.hint = "Press Ctrl+Cmd+Space to pick an emoji.";
            } else {
                this.hint = "Press Windows+. to pick an emoji.";
            }
        },

        picked(event) {
            if (event.isComposing) {
                return;
            }

            const text = this.$refs.picker.value;
            this.$refs.picker.value = "";

            for (const { segment } of this.segmenter.segment(text)) {
                if (EMOJI.test(segment)) {
                    this.$refs.picker.blur();
                    this.open = false;
                    this.add(segment);
                    return;
                }
            }
        },

        async remove(emoji) {
            if (!(await this.$wire.remove(emoji))) {
                return;
            }

            clearTimeout(this.noticeTimer);
            this.notice = `Removed ${emoji}`;
            this.noticeTimer = setTimeout(() => (this.notice = null), 6000);
        },

        undo() {
            this.$wire.undo();
            clearTimeout(this.noticeTimer);
            this.notice = null;
        },
    }));
});
