import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';
import { appBasePath } from './resources/js/lib/base-path';

export default defineConfig(({ command }) => ({
    // Vite only needs the prefix for its own emitted URLs (CSS font references,
    // dynamically imported page chunks). Blade tags get theirs from ASSET_URL.
    base: command === 'build' ? `${appBasePath}/build/` : '/',
    plugins: lazyPlugins(() => [
        {
            /**
             * Wayfinder writes literal, root-relative route URLs
             * (`url: '/login'`), which would escape the sub-directory. Prefix
             * them as the generated files are imported, so regenerating routes
             * can never lose the prefix.
             */
            name: 'wayfinder-base-path',
            enforce: 'pre' as const,
            transform(code: string, id: string) {
                if (
                    !id.endsWith('.ts') ||
                    !/[/\\]resources[/\\]js[/\\](routes|actions)[/\\]/.test(id)
                ) {
                    return null;
                }

                let prefixed = false;

                const transformed = code.replace(
                    /((?:^|\s)(?:url|action): )(['"])(\/[^'"]*)\2/g,
                    (_match, prefix: string, quote: string, path: string) => {
                        // Idempotent: this hook can run more than once per module,
                        // and Wayfinder already emits the prefixed URL for the
                        // home route (it derives it from APP_URL).
                        if (path === appBasePath || path.startsWith(`${appBasePath}/`)) {
                            return _match;
                        }

                        prefixed = true;

                        return `${prefix}${quote}${appBasePath}${path}${quote}`;
                    },
                );

                return prefixed ? { code: transformed, map: null } : null;
            },
        },
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
}));
