import { createRouter, createWebHistory } from 'vue-router';
import Login from './pages/Login.vue';
import Dashboard from './pages/Dashboard.vue';
import { useAuthStore } from './stores/auth';
import Settings from './pages/Settings.vue';

const routes = [
    { path: '/', redirect: '/dashboard' },
    { path: '/login', name: 'login', component: Login, meta: { guest: true } },
    { path: '/dashboard', name: 'dashboard', component: Dashboard, meta: { auth: true } },
    { path: '/settings', name: 'settings', component: Settings, meta: { auth: true } },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    if (!auth.initialized) {
        await auth.fetchUser();
    }

    if (to.meta.auth && !auth.user) {
        return { name: 'login' };
    }
    if (to.meta.guest && auth.user) {
        return { name: 'dashboard' };
    }
});

export default router;
