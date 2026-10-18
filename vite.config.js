import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

/**
 * In an Amp orb, Vite is the portal's front door (see .amp/services.yaml): it
 * serves assets and HMR itself and proxies every other request to Laravel, so
 * the page and its assets share one authenticated portal origin.
 */
const orbUrl =
    process.env.AMP_ORB && process.env.PUBLIC_URL
        ? new URL(process.env.PUBLIC_URL)
        : null;

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
    ],
    server: orbUrl
        ? {
              host: "127.0.0.1",
              port: Number(process.env.PORT),
              strictPort: true,
              // Written to public/hot, so @vite tags point at the portal.
              origin: orbUrl.origin,
              hmr: {
                  protocol: "wss",
                  host: orbUrl.hostname,
                  clientPort: 443,
              },
              proxy: {
                  "^/(?!@vite/|@id/|@fs/|resources/|node_modules/)": {
                      target: process.env.LARAVEL_DEV_URL,
                      // Keep the portal Host so Laravel generates portal URLs.
                      changeOrigin: false,
                  },
              },
          }
        : undefined,
});
