// Minimal service worker — satisfies PWA installability requirements.
// No caching; all requests go straight to the network.
self.addEventListener("fetch", (event) => {
    event.respondWith(fetch(event.request));
});
