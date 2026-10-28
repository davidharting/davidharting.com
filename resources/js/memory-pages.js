/**
 * How memories pages move between each other, like an iOS navigation push.
 *
 * Every memories page names its place with data-memories-page: "list", or a
 * month as "YYYY-MM". The slide follows from the two places, so links, Back
 * and Forward all agree: into a month from the list slides forward, back to
 * the list slides back, and month to month slides by date. Two tabs of one
 * month don't move at all.
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
    if (from === "list") {
        return "forward";
    }
    if (to === "list") {
        return "back";
    }
    return to > from ? "forward" : "back";
};

/**
 * iOS Safari animates its own swipe back. When it already has, the swap it
 * leads to doesn't slide again.
 */
let browserAlreadyAnimated = false;

window.addEventListener("popstate", (event) => {
    browserAlreadyAnimated = event.hasUAVisualTransition === true;
});

document.addEventListener("livewire:navigate", (event) => {
    if (!event.detail.history) {
        browserAlreadyAnimated = false;
    }
});

/**
 * Photos that are already loaded or cached show at once; the rest fade in
 * as they arrive, so a cached photo never flashes its skeleton. A page
 * restored by Back can carry a photo still marked loading from when it was
 * left, so every photo is checked again.
 */
const fadeInPhotosStillLoading = () => {
    document.querySelectorAll("img.photo-fade").forEach((img) => {
        img.classList.toggle("is-loading", !img.complete);
        if (img.complete) {
            return;
        }
        const show = () => img.classList.remove("is-loading");
        img.addEventListener("load", show, { once: true });
        img.addEventListener("error", show, { once: true });
    });
};

/**
 * A tapped month tile stays pressed until the month shows, and a page
 * restored by Back never comes back with one still pressed. A click that
 * opens the month in a new tab leaves the tile alone.
 */
const releasePressedTiles = () => {
    document
        .querySelectorAll(".is-pressed")
        .forEach((el) => el.classList.remove("is-pressed"));
};

document.addEventListener("click", (event) => {
    if (
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
    ) {
        return;
    }
    event.target
        .closest("a[data-holds-press]")
        ?.closest("[data-month-tile]")
        ?.classList.add("is-pressed");
});

/**
 * Phones never hover, and iOS presses a link only when the finger lifts, so
 * Livewire's own prefetch starts too late there. Each month link marked
 * data-prefetch-in-view is prefetched as it scrolls into view, again every
 * 25 seconds while it stays there (Livewire drops a prefetch after 30), and
 * again the moment a finger lands on it. (Listening for touches also lets
 * iOS Safari apply :active, which the pressed tile needs.)
 *
 * Livewire has no prefetch API, so this starts the prefetch its
 * wire:navigate.hover listens for by sending the link a mouseenter.
 */
const PREFETCH_AGAIN_AFTER_MILLISECONDS = 25000;

const prefetch = (link) => link.dispatchEvent(new MouseEvent("mouseenter"));

const linksInView = new Set();

const prefetchWhenInView = new IntersectionObserver(
    (entries) => {
        for (const entry of entries) {
            if (entry.isIntersecting) {
                linksInView.add(entry.target);
                prefetch(entry.target);
            } else {
                linksInView.delete(entry.target);
            }
        }
    },
    { rootMargin: "200px 0px" },
);

setInterval(
    () => linksInView.forEach(prefetch),
    PREFETCH_AGAIN_AFTER_MILLISECONDS,
);

document.addEventListener(
    "touchstart",
    (event) => {
        const link = event.target.closest("a[data-prefetch-in-view]");
        if (link) {
            prefetch(link);
        }
    },
    { passive: true },
);

const watchLinksToPrefetch = () => {
    prefetchWhenInView.disconnect();
    linksInView.clear();
    document
        .querySelectorAll("a[data-prefetch-in-view]")
        .forEach((link) => prefetchWhenInView.observe(link));
};

document.addEventListener("livewire:navigating", (event) => {
    const from = placeOfPage();

    event.detail.onSwap(() => {
        const movement = browserAlreadyAnimated
            ? "none"
            : movementBetween(from, placeOfPage());
        browserAlreadyAnimated = false;
        if (movement !== null) {
            document.documentElement.dataset.memoriesMovement = movement;
        }
        fadeInPhotosStillLoading();
    });
});

document.addEventListener("livewire:navigated", () => {
    releasePressedTiles();
    watchLinksToPrefetch();
});
window.addEventListener("pageshow", releasePressedTiles);

fadeInPhotosStillLoading();
