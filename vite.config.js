// 
// ================================================================================
// DEVELOPER DOCUMENTATION - vite.config.js
// ================================================================================
// Purpose: Vite configuration for Laravel with Tailwind CSS v4 plugin.
// Stack: JavaScript - Vite build tool configuration.
// Dependencies:
//   - laravel-vite-plugin: Provides Laravel integration
//   - @tailwindcss/vite: Tailwind CSS v4 bundler
// Process Logic:
//   - Entry points: resources/css/app.css and resources/js/app.js
//   - Hot reload: Watches for file changes (ignores storage/framework/views)
// AI Context: PROJECT_CONTEXT: Frontend-focused Laravel/Tailwind build. AI Analysis integration in progress.
// ================================================================================

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
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