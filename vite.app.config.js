// Build of the screens for the Android app (Capacitor). The website keeps using vite.config.js.
// Run: VITE_NATIVE=1 npx vite build -c vite.app.config.js  -> native-www/ (copied into the APK)
import { defineConfig } from 'vite';
import { fileURLToPath } from 'node:url';
import inertia from '@inertiajs/vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

const r = (p) => fileURLToPath(new URL(p, import.meta.url));

export default defineConfig({
    root: r('./resources/native'),
    base: '/',
    publicDir: false,
    resolve: { alias: { '@': r('./resources/js') } },
    plugins: [
        inertia(),
        vue({
            template: {
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
        tailwindcss(),
    ],
    build: {
        outDir: r('./native-www'),
        emptyOutDir: true,
        // Same /build/assets path as the website, served from the APK instead of the server.
        assetsDir: 'build/assets',
    },
});
