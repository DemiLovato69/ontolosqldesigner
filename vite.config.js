import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/css/app.css'
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    server: {
        host: '0.0.0.0', // Allow Docker network access
        port: 5173,
        strictPort: true, // Fail if port 5173 is unavailable
        hmr: {
            protocol: 'ws', // WebSocket for HMR
            host: 'localhost', // Match Nginx proxy
            port: 5173,
        },
    },
});
