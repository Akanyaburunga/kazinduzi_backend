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
                path: 'riddles/:id',
                name: 'admin.riddles.show',
                component: () => import('./views/riddles/Show.vue'),
                meta: { title: 'Riddle Analytics' },
            },
            {
                path: 'proverbs',
                name: 'admin.proverbs.index',
                component: () => import('./views/proverbs/Index.vue'),
                meta: { title: 'Proverbs' },
            },
            {
                path: 'proverbs/:id',
                name: 'admin.proverbs.show',
                component: () => import('./views/proverbs/Show.vue'),
                meta: { title: 'Proverb Analytics' },
            },
            {
                path: 'jokes',
                name: 'admin.jokes.index',
                component: () => import('./views/jokes/Index.vue'),
                meta: { title: 'Jokes' },
            },
            {
                path: 'jokes/:id',
                name: 'admin.jokes.show',
                component: () => import('./views/jokes/Show.vue'),
                meta: { title: 'Joke Analytics' },
            },
            {
                path: 'categories',
                name: 'admin.categories.index',
                component: () => import('./views/categories/Index.vue'),
                meta: { title: 'Categories' },
            },
            {
                path: 'tags',
                name: 'admin.tags.index',
                component: () => import('./views/tags/Index.vue'),
                meta: { title: 'Tags' },
            },
            {
                path: 'achievements',
                name: 'admin.achievements.index',
                component: () => import('./views/achievements/Index.vue'),
                meta: { title: 'Badges' },
            },
            {
                path: 'analytics',
                name: 'admin.analytics.index',
                component: () => import('./views/analytics/Index.vue'),
                meta: { title: 'Analytics' },
            },
            {
                path: 'settings',
                name: 'admin.settings.index',
                component: () => import('./views/settings/Index.vue'),
                meta: { title: 'Settings' },
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
        document.title = `${to.meta.title} | Rinjora`;
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
