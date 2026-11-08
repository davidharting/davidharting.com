/**
 * Makes the photo button in Filament's markdown editors work on iPhone.
 *
 * iPhone Safari sends 0 bytes for photos picked from the library or taken
 * with the camera when the picked file goes straight into an upload, so the
 * server gets an empty file and the editor quietly gives up. (The memory
 * editor works around the same thing in memory-photos.js.) Copying each
 * picked file into memory first gives Safari real bytes to send.
 *
 * This catches the editor's file input changing before the editor sees it,
 * swaps in the copies, and hands the change back to the editor. Pasted and
 * dropped images are already in memory, so they're left alone.
 */
const COPIED = "markdownEditorAttachmentsCopied";

document.addEventListener(
    "change",
    async (event) => {
        const input = event.target;

        if (
            !(input instanceof HTMLInputElement) ||
            !input.matches(".fi-fo-markdown-editor input.imageInput") ||
            !input.files?.length
        ) {
            return;
        }

        if (input.dataset[COPIED]) {
            delete input.dataset[COPIED];
            return;
        }

        event.stopImmediatePropagation();

        const copies = new DataTransfer();

        for (const file of Array.from(input.files)) {
            try {
                copies.items.add(
                    new File([await file.arrayBuffer()], file.name, {
                        type: file.type,
                        lastModified: file.lastModified,
                    }),
                );
            } catch {
                copies.items.add(file);
            }
        }

        input.files = copies.files;
        input.dataset[COPIED] = "true";
        input.dispatchEvent(new Event("change"));
    },
    true,
);
