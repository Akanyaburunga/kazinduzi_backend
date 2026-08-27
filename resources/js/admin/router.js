import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth.js';

const routes = [
    {
        path: '/admin',
        component: () => import('./layouts/AdminLayout.vue'),
        children: [
            {
                path: '',
                name: 'admin.dashboard',
                component: () => import('./views/Dashboard.vue'),
                meta: { title: 'Dashboard' },
            },
            {
                path: 'riddles',
                name: 'admin.riddles.index',
                component: () => import('./views/riddles/Index.vue'),
                meta: { title: 'Riddles' },
            },
            {
                path: 'categories',
                name: 'admin.categories.index',
                component: () => import('./views/categories/Index.vue'),
                meta: { title: 'Categories' },
            },
        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!auth.initialised) {
        await auth.fetchSession();
    }

    if (to.meta?.title) {
        document.title = `${to.meta.title} | Kazinduzi Admin`;
    }

    if (!auth.isAdmin) {
        return { path: to.path.startsWith('/admin') ? '/admin' : to.fullPath };
    }
});

export default router;
