import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

/*
 * The portal's stylesheets are built by Vite so every deploy ships content-
 * hashed file names (public/build/assets/lms-<hash>.css). Browsers cache them
 * for a year (see deploy/nginx-ecenter.conf) and still pick up a change the
 * moment it is released -- no "press Ctrl+F5 after a deploy".
 *
 * Each layout (resources/views/lms/*layout.blade.php) loads its own subset
 * with @vite(), in cascade order: lms.css is the base the others build on.
 */
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/lms.css',
                'resources/css/auth.css',
                'resources/css/learning.css',
                'resources/css/classroom.css',
                'resources/css/classroom-fullscreen.css',
                'resources/css/classroom-icons.css',
                'resources/css/classroom-light.css',
                'resources/css/instructor.css',
                'resources/css/instructor-forms.css',
            ],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
