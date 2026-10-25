/**
 * The reaction row's picker and toast. A page can't open the phone's emoji
 * keyboard, so + focuses a hidden input and the first emoji typed into it is
 * sent, after which the keyboard closes. Anything else typed is dropped.
 *
 * The emoji test here is looser than the server's, in
 * App\Memories\MemoryReactionWriter, which has the final say.
 */
const EMOJI = /[\p{Extended_Pictographic}\p{Regional_Indicator}\u{20E3}]/u;

document.addEventListener("alpine:init", () => {
    window.Alpine.data("memoryReactions", () => ({
        hint: null,
        notice: null,
        noticeTimer: null,
        segmenter: new Intl.Segmenter(),

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
                    this.$wire.add(segment);
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
