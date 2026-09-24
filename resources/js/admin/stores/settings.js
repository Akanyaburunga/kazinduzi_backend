import { defineStore } from 'pinia';
import axios from '../bootstrap.js';
import { useToastStore } from './toast.js';

const GUEST_LIMIT_MODES = ['sokwe', 'hera', 'tuja'];

function emptyGuestLimits() {
    return Object.fromEntries(
        GUEST_LIMIT_MODES.map((mode) => [mode, { mode, name: mode, limit: 0, default: 0, configured: false }]),
    );
}

export const useSettingsStore = defineStore('settings', {
    state: () => ({
        loading: false,
        saving: false,
        guestLimits: emptyGuestLimits(),
    }),

    actions: {
        async fetchGuestLimits() {
            this.loading = true;
            try {
                const { data } = await axios.get('/admin/api/settings/guest-limits');
                this.guestLimits = { ...emptyGuestLimits(), ...data.data };
                return data;
            } finally {
                this.loading = false;
            }
        },

        async saveGuestLimits(payload) {
            this.saving = true;
            const toast = useToastStore();
            try {
                const { data } = await axios.put('/admin/api/settings/guest-limits', payload);
                toast.success('Guest round limits saved.');
                await this.fetchGuestLimits();
                return data;
            } catch (error) {
                toast.error(error.response?.data?.message ?? 'Failed to save guest round limits.');
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async resetGuestLimits() {
            this.saving = true;
            const toast = useToastStore();
            try {
                const { data } = await axios.post('/admin/api/settings/guest-limits/reset');
                toast.success('Guest round limits reset.');
                await this.fetchGuestLimits();
                return data;
            } catch (error) {
                toast.error(error.response?.data?.message ?? 'Failed to reset guest round limits.');
                throw error;
            } finally {
                this.saving = false;
            }
        },
    },
});