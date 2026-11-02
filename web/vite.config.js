import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // The stylesheet, and the one script in the application: the admin
            // dashboard's poll, loaded only on that page (System_Plan.md §3).
            input: ['resources/css/app.css', 'resources/js/pages/admin-dashboard.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
