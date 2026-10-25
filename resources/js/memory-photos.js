/**
 * The photos part of the memory editor: picking photos, uploading them one
 * at a time, the caption sheet and the toast. The server renders the photos
 * that have landed; this keeps the ones still on their way.
 *
 * Picked files are copied into memory before uploading, because iPhone
 * Safari otherwise sends 0 bytes for photos picked from the library.
 *
 * Livewire tells nobody when one of its upload requests is lost, so an
 * upload that goes quiet for too long is given up on and offered again.
 */
const QUIET_UPLOAD_MILLISECONDS = 45000;

document.addEventListener("alpine:init", () => {
    window.Alpine.store("memoryUploads", { pending: 0, abandoned: false });

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
        watchdog: null,

        init() {
            this.$watch("uploads", () => {
                this.$store.memoryUploads.pending = this.uploads.filter(
                    (upload) => !upload.failed,
                ).length;
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
                let copy;

                try {
                    copy = new File([await file.arrayBuffer()], file.name, {
                        type: file.type,
                    });
                } catch {
                    this.say(
                        `${file.name} couldn't be read, so it was skipped.`,
                    );
                    continue;
                }

                this.uploads.push({
                    key: this.nextKey++,
                    file: copy,
                    preview: URL.createObjectURL(copy),
                    progress: 0,
                    failed: false,
                });
                this.sendNext();
            }
        },

        sendNext() {
            if (this.current) return;

            const upload = this.uploads.find((waiting) => !waiting.failed);
            if (!upload) return;

            this.current = upload;
            this.watch(upload);
            this.$wire.upload(
                "upload",
                upload.file,
                () => {},
                () => this.fail(upload),
                (event) => {
                    upload.progress = event.detail.progress;
                    this.watch(upload);
                },
                () => {},
            );
        },

        /**
         * Give up on the upload if nothing more is heard of it for a while.
         */
        watch(upload) {
            clearTimeout(this.watchdog);
            this.watchdog = setTimeout(() => {
                this.$wire.cancelUpload("upload");
                this.fail(upload);
            }, QUIET_UPLOAD_MILLISECONDS);
        },

        fail(upload) {
            if (this.current !== upload) return;

            clearTimeout(this.watchdog);
            upload.failed = true;
            this.current = null;
            this.sendNext();
        },

        /**
         * The server added the upload in flight, or turned it down.
         */
        handled(photoId) {
            const upload = this.current;
            if (!upload) return;

            clearTimeout(this.watchdog);

            if (photoId) {
                this.previews[photoId] = upload.preview;
            } else {
                URL.revokeObjectURL(upload.preview);
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
            URL.revokeObjectURL(upload.preview);
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
