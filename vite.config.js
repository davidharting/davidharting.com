import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
    ],
    ...orbConfig(),
});

/**
 * Config added only inside an Amp orb; empty everywhere else.
 *
 * In an orb, Vite is the portal's front door (see .amp/services.yaml): it
 * serves assets and HMR itself and proxies every other request to Laravel, so
 * the page and its assets share one authenticated portal origin.
 */
function orbConfig() {
    if (!process.env.AMP_ORB || !process.env.PUBLIC_URL) {
        return {};
    }

    const portal = new URL(process.env.PUBLIC_URL);

    return {
        server: {
            host: "127.0.0.1",
            port: Number(process.env.PORT),
            strictPort: true,
            // Written to public/hot, so @vite tags point at the portal.
            origin: portal.origin,
            hmr: {
                protocol: "wss",
                host: portal.hostname,
                clientPort: 443,
            },
            proxy: {
                "^/(?!@vite/|@id/|@fs/|resources/|node_modules/)": {
                    target: process.env.LARAVEL_DEV_URL,
                    // Keep the portal Host so Laravel generates portal URLs.
                    changeOrigin: false,
                },
            },
        },
    };
}
