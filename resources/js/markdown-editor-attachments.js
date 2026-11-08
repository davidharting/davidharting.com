import { copyIntoMemory } from "./copy-into-memory";

/**
 * Makes the photo button in Filament's markdown editors work on iPhone.
 *
 * iPhone Safari sends 0 bytes for photos picked from the library or taken
 * with the camera unless they're copied into memory first (see
 * copy-into-memory.js), so the server got an empty file and the editor
 * quietly gave up.
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
                copies.items.add(await copyIntoMemory(file));
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
