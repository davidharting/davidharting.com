/**
 * The photos part of the memory editor: picking photos, uploading them one
 * at a time, the caption sheet and the toast. The server renders the photos
 * that have landed; this keeps the ones still on their way.
 *
 * Picked files are copied into memory before uploading, because iPhone
 * Safari otherwise sends 0 bytes for photos picked from the library.
 */
document.addEventListener("alpine:init", () => {
    window.Alpine.store("memoryUploads", { pending: 0 });

    window.Alpine.data("memoryPhotos", (max) => ({
        max,
        uploads: [],
        current: null,
        nextKey: 0,
        previews: {},
        sheet: null,
        notice: null,
        removedPhotoId: null,
        noticeTimer: null,

        init() {
            this.$watch("uploads", () => {
                this.$store.memoryUploads.pending = this.uploads.length;
            });
        },

        get room() {
            return this.max - this.$wire.photoCount - this.uploads.length;
        },

        async pick(event) {
            const files = Array.from(event.target.files);
            event.target.value = "";
            const room = this.room;

            if (room <= 0) {
                this.say(
                    "That's the most a memory can hold. Remove one to add another.",
                );
                return;
            }

            if (files.length > room) {
                this.say(
                    `A memory holds ${this.max} photos, so only the first ${room} of the ${files.length} you picked were added.`,
                );
            }

            for (const file of files.slice(0, room)) {
                const copy = new File([await file.arrayBuffer()], file.name, {
                    type: file.type,
                });
                this.uploads.push({
                    key: this.nextKey++,
                    file: copy,
                    preview: URL.createObjectURL(copy),
                    progress: 0,
                    failed: false,
                });
            }

            this.sendNext();
        },

        sendNext() {
            if (this.current) return;

            const upload = this.uploads.find((waiting) => !waiting.failed);
            if (!upload) return;

            this.current = upload;
            this.$wire.upload(
                "upload",
                upload.file,
                () => {},
                () => {
                    upload.failed = true;
                    this.current = null;
                    this.sendNext();
                },
                (event) => (upload.progress = event.detail.progress),
            );
        },

        /**
         * The server added the upload in flight, or turned it down.
         */
        handled(photoId) {
            const upload = this.current;
            if (!upload) return;

            if (photoId) {
                this.previews[photoId] = upload.preview;
            }

            this.uploads = this.uploads.filter((other) => other !== upload);
            this.current = null;
            this.sendNext();
        },

        retry(upload) {
            upload.failed = false;
            upload.progress = 0;
            this.sendNext();
        },

        dismiss(upload) {
            this.uploads = this.uploads.filter((other) => other !== upload);
        },

        removed(photoId) {
            this.sheet = null;
            this.say("Photo removed.", photoId);
        },

        undoRemove() {
            this.$wire.restorePhoto(this.removedPhotoId);
            this.hideNotice();
        },

        say(text, removedPhotoId = null) {
            clearTimeout(this.noticeTimer);
            this.notice = text;
            this.removedPhotoId = removedPhotoId;
            this.noticeTimer = setTimeout(() => this.hideNotice(), 6000);
        },

        hideNotice() {
            this.notice = null;
            this.removedPhotoId = null;
        },
    }));
});
