import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';
import symfonyPlugin from 'vite-plugin-symfony';

export default defineConfig({
    plugins: [
        tailwindcss(),
        symfonyPlugin({
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            input: {
                app: './assets/app.js',
            },
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: 'http://localhost:5173',
        watch: {
            usePolling: true,
        },
        hmr: {
            host: 'localhost',
            clientPort: 5173,
        },
    },
});
