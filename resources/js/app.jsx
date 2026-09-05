import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import Layout from './Layouts/AppLayout';

const appName = import.meta.env.VITE_APP_NAME || 'Creative Tree';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        const page = pages[`./Pages/${name}.jsx`];
        page.default.layout ??= name.startsWith('Auth/') ? undefined : (p) => <Layout>{p}</Layout>;
        return page;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#0f766e', showSpinner: false },
});
