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
                this.user = data.user;
                this.admin = data.admin;
                this.authenticated = data.authenticated;
            } catch (error) {
                this.user = null;
                this.admin = false;
                this.authenticated = false;
            } finally {
                this.loading = false;
                this.initialised = true;
            }
        },
    },
});
