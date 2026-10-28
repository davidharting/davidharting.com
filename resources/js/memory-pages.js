/**
 * How memories pages move between each other, like an iOS navigation push.
 *
 * Every memories page names its place with data-memories-page: "list", a
 * month as "YYYY-MM", or "editor". The slide follows from the two places, so
 * links, Back and Forward all agree: into a month from the list slides
 * forward, back to the list slides back, month to month slides by date, and
 * the editor rises in. Two tabs of one month don't move at all.
 *
 * Livewire replaces <html>'s attributes during the swap, so the direction is
 * set after it. The browser styles the view transition after the swap too,
 * so the CSS in app.css still sees it.
 */
const placeOfPage = () =>
    document.querySelector("[data-memories-page]")?.dataset.memoriesPage ??
    null;

const movementBetween = (from, to) => {
    if (from === null || to === null) {
        return null;
    }
    if (from === to) {
        return "none";
    }
    if (to === "editor") {
        return "rise";
    }
    if (from === "list") {
        return "forward";
    }
    if (to === "list") {
        return "back";
    }
    return to > from ? "forward" : "back";
};

/**
 * Photos that are already loaded or cached show at once; the rest fade in
 * as they arrive, so a cached photo never flashes its skeleton.
 */
const fadeInPhotosStillLoading = () => {
    document.querySelectorAll("img.photo-fade").forEach((img) => {
        if (img.complete) {
            return;
        }
        img.classList.add("is-loading");
        const show = () => img.classList.remove("is-loading");
        img.addEventListener("load", show, { once: true });
        img.addEventListener("error", show, { once: true });
    });
};

/**
 * A tapped month tile stays pressed until the month shows, and a page
 * restored by Back never comes back with one still pressed.
 */
const releasePressedTiles = () => {
    document
        .querySelectorAll(".is-pressed")
        .forEach((el) => el.classList.remove("is-pressed"));
};

document.addEventListener("click", (event) => {
    event.target
        .closest("a[data-holds-press]")
        ?.closest("[data-month-tile]")
        ?.classList.add("is-pressed");
});

// iOS Safari only applies :active while something listens for touches.
document.addEventListener("touchstart", () => {}, { passive: true });

document.addEventListener("livewire:navigating", (event) => {
    const from = placeOfPage();

    event.detail.onSwap(() => {
        const movement = movementBetween(from, placeOfPage());
        if (movement !== null) {
            document.documentElement.dataset.memoriesMovement = movement;
        }
        fadeInPhotosStillLoading();
    });
});

document.addEventListener("livewire:navigated", releasePressedTiles);
window.addEventListener("pageshow", releasePressedTiles);

fadeInPhotosStillLoading();
