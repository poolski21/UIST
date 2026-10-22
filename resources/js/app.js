import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
    title: (title) => `${title} - UIST2its`,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        return pages[`./Pages/${name}.jsx`];
    },
    setup({ el, App, props }) {
        createRoot(el).render(createElement(App, props));
    },
    progress: { color: '#1a93e8' },
});
