import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5's own bundled Sass still uses the legacy
                // red()/green()/blue() color API, which is deprecated in
                // Dart Sass and floods every build with warnings that
                // originate entirely inside node_modules, not our app.scss.
                // quietDeps silences warnings from dependencies while still
                // surfacing any real deprecation in our own styles.
                quietDeps: true,
                silenceDeprecations: ['color-functions', 'import', 'global-builtin'],
            },
        },
    },
});
