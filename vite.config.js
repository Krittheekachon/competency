import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    esbuild: {
        jsx: 'transform',
        jsxFactory: 'h',
        jsxFragment: 'VueFragment',
        jsxInject: "import { h, Fragment as VueFragment } from 'vue'",
    },
    optimizeDeps: {
        rolldownOptions: {
            transform: {
                jsx: {
                    runtime: 'automatic',
                    importSource: 'vue',
                },
            },
        },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
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
});
