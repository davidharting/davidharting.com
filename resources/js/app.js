import "./bootstrap";
import "./memory-photos";
import "./memory-reactions";

// Import only hljs core + specific languages to reduce bundle size
import hljs from "highlight.js/lib/core";
import bash from "highlight.js/lib/languages/bash";
import json from "highlight.js/lib/languages/json";
import yaml from "highlight.js/lib/languages/yaml";
import sql from "highlight.js/lib/languages/sql";
import python from "highlight.js/lib/languages/python";

hljs.registerLanguage("bash", bash);
hljs.registerLanguage("json", json);
hljs.registerLanguage("yaml", yaml);
hljs.registerLanguage("sql", sql);
hljs.registerLanguage("python", python);

// Apply syntax highlighting to all code blocks on page load
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("pre code").forEach((block) => {
        hljs.highlightElement(block);
    });
});

// Register service worker for PWA installability (no offline caching)
if ("serviceWorker" in navigator) {
    navigator.serviceWorker
        .register("/sw.js")
        .catch((err) =>
            console.error("Service worker registration failed:", err),
        );
}

// PROTOTYPE (mobile polish, #334): a tile stays pressed while its month
// loads; clear that when Back restores the list from the back/forward cache.
window.addEventListener("pageshow", () => {
    document
        .querySelectorAll(".is-pressed")
        .forEach((el) => el.classList.remove("is-pressed"));
});

// PROTOTYPE (mobile polish, #334): phones never hover, so start Livewire's
// hover prefetch for each month as its tile scrolls into view, and again
// the moment a finger lands on it.
const prefetchMonth = (link) =>
    link.dispatchEvent(new MouseEvent("mouseenter"));
const prefetcher = new IntersectionObserver(
    (entries) => {
        for (const entry of entries) {
            if (!entry.isIntersecting) continue;
            prefetcher.unobserve(entry.target);
            prefetchMonth(entry.target);
        }
    },
    { rootMargin: "200px 0px" },
);
const watchMonthLinks = () =>
    document.querySelectorAll("a[data-prefetch]").forEach((link) => {
        prefetcher.observe(link);
        link.addEventListener("touchstart", () => prefetchMonth(link), {
            passive: true,
        });
    });
document.addEventListener("livewire:navigated", watchMonthLinks);

// PROTOTYPE (mobile polish, #334): each month shows a different transition
// between the list and its page. The tapped tile (or the month page being
// left) names it on <html data-vt> before Livewire snapshots the old page,
// and it's put back after the swap, which replaces <html>'s attributes.
let transition = null;
document.addEventListener(
    "click",
    (event) => {
        const link = event.target.closest("a[data-vt]");
        transition = link
            ? {
                  vt: link.dataset.vt,
                  dir: link.dataset.vtDir ?? "in",
                  clicked: true,
                  month: link.closest("[data-vt-month]")?.dataset.vtMonth,
              }
            : null;
    },
    true,
);
const applyTransition = () => {
    const html = document.documentElement;
    if (!transition || transition.vt === "title") {
        delete html.dataset.vt;
        delete html.dataset.vtDir;
        return;
    }
    html.dataset.vt = transition.vt;
    html.dataset.vtDir = transition.dir;
    document
        .querySelectorAll("[data-vt-keep]:not(article)")
        .forEach((el) => delete el.dataset.vtKeep);
    document
        .querySelectorAll(`[data-vt-month="${transition.month}"]`)
        .forEach((el) => (el.dataset.vtKeep = ""));
};
document.addEventListener("livewire:navigating", (event) => {
    const page = document.querySelector("[data-page-vt]");
    if (page && !transition) {
        transition = {
            vt: page.dataset.pageVt,
            dir: "out",
            month: page.dataset.vtMonth,
        };
    }
    applyTransition();
    event.detail.onSwap(() => {
        const page = document.querySelector("[data-page-vt]");
        if (transition && !transition.clicked && page) {
            // Back or Forward between two months slides by date order;
            // switching tabs within a month doesn't slide at all.
            const from = transition.month;
            const to = page.dataset.vtMonth;
            transition =
                from === to
                    ? null
                    : { ...transition, dir: to > from ? "in" : "out" };
        }
        if (transition?.clicked && transition.dir === "in" && !page) {
            transition = null;
        }
        applyTransition();
        transition = null;
    });
});
