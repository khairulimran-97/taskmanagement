import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { toast } from 'vue-sonner';
import { initializeTheme } from './composables/useAppearance';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

interface FlashMessage {
    success?: string | null;
    error?: string | null;
    warning?: string | null;
    info?: string | null;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

router.on('success', (event) => {
    try {
        const page = event.detail.page;
        const flash = page?.props?.flash as FlashMessage;

        if (flash?.success) {
            toast.success(flash.success, {
                style: {
                    background: '#10b981',
                    color: 'white',
                    border: '1px solid #059669'
                },
                duration: 4000
            });
        }
        if (flash?.error) {
            toast.error(flash.error, {
                style: {
                    background: '#ef4444',
                    color: 'white',
                    border: '1px solid #dc2626'
                },
                duration: 4000
            });
        }
        if (flash?.warning) {
            toast.warning(flash.warning, {
                style: {
                    background: '#f59e0b',
                    color: 'white',
                    border: '1px solid #d97706'
                },
                duration: 4000
            });
        }
        if (flash?.info) {
            toast.info(flash.info, {
                style: {
                    background: '#3b82f6',
                    color: 'white',
                    border: '1px solid #2563eb'
                },
                duration: 4000
            });
        }
    } catch (error) {
        console.error('Flash message error:', error);
    }
});

// This will set light / dark mode on page load...
initializeTheme();
