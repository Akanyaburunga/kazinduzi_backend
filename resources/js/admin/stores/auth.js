import { defineStore } from 'pinia';
import axios from '../bootstrap.js';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        admin: false,
        authenticated: false,
        loading: false,
        initialised: false,
    }),

    getters: {
        isAdmin: (state) => state.admin,
        isAuthenticated: (state) => state.authenticated,
    },

    actions: {
        async fetchSession() {
            this.loading = true;
            try {
                const { data } = await axios.get('/admin/api/session');
                this.apply(data);
            } catch (error) {
                this.reset();
            } finally {
                this.loading = false;
                this.initialised = true;
            }
        },

        async login(credentials) {
            this.loading = true;
            try {
                const { data } = await axios.post('/admin/api/login', credentials);
                this.apply(data);
                return { ok: true, data };
            } catch (error) {
                this.reset();
                return {
                    ok: false,
                    message: error.response?.data?.message,
                    errors: error.response?.data?.errors,
                };
            } finally {
                this.loading = false;
                this.initialised = true;
            }
        },

        async logout() {
            try {
                await axios.post('/admin/api/logout');
            } finally {
                this.reset();
            }
        },

        apply(data) {
            this.user = data.user;
            this.admin = data.admin;
            this.authenticated = data.authenticated;
        },

        reset() {
            this.user = null;
            this.admin = false;
            this.authenticated = false;
        },
    },
});
