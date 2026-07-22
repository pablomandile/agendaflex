import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';
import cssInjectedByJs from 'vite-plugin-css-injected-by-js';

// Build del widget embebible: un único JS (CSS inline) con nombre estable,
// SIN laravel-vite-plugin (no usa manifest/hash) para poder incluirlo
// con un simple <script> en sitios de terceros.
export default defineConfig({
    plugins: [vue(), cssInjectedByJs()],
    define: {
        'process.env.NODE_ENV': '"production"',
    },
    // El outDir vive dentro de public/: desactivar publicDir evita copiarse a sí mismo
    publicDir: false,
    build: {
        outDir: 'public/widget',
        emptyOutDir: true,
        cssCodeSplit: false,
        lib: {
            entry: 'resources/widget/main.ts',
            name: 'AgendaflexWidget',
            formats: ['iife'],
            fileName: () => 'agendaflex-widget.js',
            cssFileName: 'agendaflex-widget',
        },
        rollupOptions: {
            output: {
                inlineDynamicImports: true,
                entryFileNames: 'agendaflex-widget.js',
            },
        },
    },
});
