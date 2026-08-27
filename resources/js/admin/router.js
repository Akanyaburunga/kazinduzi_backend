import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth.js';

const routes = [
    {
        path: '/admin/login',
        name: 'admin.login',
        component: () => import('./views/Login.vue'),
        meta: { title: 'Login' },
    },
    {
        path: '/admin',
        component: () => import('./layouts/AdminLayout.vue'),
        meta: { requiresAdmin: true },
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

    const isLogin = to.name === 'admin.login';

    if (!auth.isAdmin) {
        if (!isLogin) {
            return { name: 'admin.login' };
        }
        return;
    }

    if (isLogin) {
        return { name: 'admin.dashboard' };
    }
});

export default router;
