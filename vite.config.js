import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/components/layout.css',
                'resources/css/components/dashboard.css',
                'resources/css/dashboard/admin.css',
                'resources/css/dashboard/dosen-mahasiswa.css',
                'resources/css/dashboard/task-panel.css',
                'resources/css/dashboard/notifikasi.css',
                'resources/css/dashboard/nilai.css',
                'resources/css/components/courses.css',
                'resources/css/components/buttons.css',
                'resources/css/courses/materials.css',
                'resources/css/courses/enrollments.css',
                'resources/css/courses/grading.css',
                'resources/css/courses/assignments.css',
                'resources/css/courses/forms.css',
                'resources/css/users/users.css',
                'resources/css/errors/errors.css',
                'resources/css/tentang.css',
                'resources/css/welcome.css',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});