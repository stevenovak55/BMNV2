import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    // Base public path — overridden at build time if needed via CLI flag.
    // In production the assets are served from the theme's dist directory.
    base: './',

    build: {
        // Output compiled assets into the theme's dist folder.
        outDir: resolve(__dirname, 'assets/dist'),
        emptyOutDir: true,

        // Generate a manifest so the PHP helper can map entry names to hashed filenames.
        manifest: true,

        rollupOptions: {
            input: {
                main: resolve(__dirname, 'assets/src/ts/main.ts'),
                style: resolve(__dirname, 'assets/src/scss/main.scss'),
            },
        },

        // Target modern browsers.
        target: 'es2022',

        // Generate source maps for easier debugging.
        sourcemap: true,
    },

    server: {
        port: 5173,
        strictPort: true,

        // Allow the WordPress site (running on a different origin) to load dev assets.
        cors: true,

        // Ensure HMR works when WordPress is on a different host.
        origin: 'http://localhost:5173',
    },

    css: {
        devSourcemap: true,
    },
});
