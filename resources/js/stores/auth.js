import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        initialized: false,
    }),
    actions: {
        async fetchUser() {
            try {
                const { data } = await axios.get('/api/user');
                this.user = data;
            } catch (e) {
                this.user = null;
            } finally {
                this.initialized = true;
            }
        },
        async login(email, password) {
            await axios.get('/sanctum/csrf-cookie');
            await axios.post('/api/login', { email, password });
            await this.fetchUser();
        },
        async logout() {
            await axios.post('/api/logout');
            this.user = null;
        },
    },
});
