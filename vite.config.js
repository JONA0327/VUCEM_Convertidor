import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/upload.css',
                'resources/js/upload.js',
                'resources/js/extract_images.js',
                'resources/js/compress.js',
                'resources/js/merge.js'
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
