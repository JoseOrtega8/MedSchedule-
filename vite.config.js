import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/agenda.css',
                'resources/css/especialidades.css',
                'resources/css/horarios.css',
                'resources/css/doctor-profile.css',
                'resources/css/admin-logs.css',
                'resources/css/admin-rbac.css',
                'resources/js/app.js',
                'resources/js/about.js',
                'resources/js/topbar-date.js',
                'resources/js/dashboard.js',
                'resources/js/agenda.js',
                'resources/js/especialidades.js',
                'resources/js/horarios.js',
                'resources/js/doctor-profile.js',
                'resources/js/admin-logs.js',
                'resources/js/admin-rbac.js',
            ],
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
