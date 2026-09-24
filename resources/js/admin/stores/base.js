import { defineStore } from 'pinia';
import axios from '../bootstrap.js';
import { useToastStore } from './toast.js';

/**
 * Generic CRUD store factory.
 *
 * Usage:
 *   import { useCrudStore } from '../stores/base.js';
 *   const useRiddlesStore = useCrudStore('riddles', '/admin/api/riddles');
 *
 * Exposes items, loading, filters (search/sort/page), and CRUD actions wired
 * to the toasts. Adaptable to any future platform module via its own options.
 */
export function useCrudStore(name, endpoint, options = {}) {
    const {
        idField = 'id',
        perPage = 15,
        initialSort = null,
    } = options;

    return defineStore(`crud_${name}`, {
        state: () => ({
            items: [],
            meta: null,
            loading: false,
            saving: false,
            search: '',
            sortField: initialSort?.field ?? null,
            sortDir: initialSort?.dir ?? 'asc',
            page: 1,
            perPage,
            filters: options.filters ?? {},
        }),

        actions: {
            buildParams() {
                const params = {
                    page: this.page,
                    per_page: this.perPage,
                    search: this.search || undefined,
                    sort: this.sortField || undefined,
                    dir: this.sortDir || undefined,
                };
                for (const [key, value] of Object.entries(this.filters)) {
                    if (value !== '' && value !== null && value !== undefined) {
                        params[key] = value;
                    }
                }
                return params;
            },

            async fetch() {
                this.loading = true;
                try {
                    const { data } = await axios.get(endpoint, { params: this.buildParams() });
                    const payload = data.data;
                    this.items = Array.isArray(payload) ? payload : (payload?.data ?? []);
                    this.meta = data.meta ?? (Array.isArray(payload) ? null : (payload ?? null));
                    return data;
                } finally {
                    this.loading = false;
                }
            },

            async save(payload, id = null) {
                this.saving = true;
                const toast = useToastStore();
                try {
                    let data;
                    if (id) {
                        ({ data } = await axios.put(`${endpoint}/${id}`, payload));
                    } else {
                        ({ data } = await axios.post(endpoint, payload));
                    }
                    toast.success(id ? 'Updated successfully.' : 'Created successfully.');
                    await this.fetch();
                    return data.data;
                } catch (error) {
                    toast.error(this.errorMessage(error));
                    throw error;
                } finally {
                    this.saving = false;
                }
            },

            async remove(id) {
                this.saving = true;
                const toast = useToastStore();
                try {
                    await axios.delete(`${endpoint}/${id}`);
                    toast.success('Deleted successfully.');
                    await this.fetch();
                } catch (error) {
                    toast.error(this.errorMessage(error));
                    throw error;
                } finally {
                    this.saving = false;
                }
            },

            async custom(actionEndpoint, successMessage) {
                const toast = useToastStore();
                try {
                    await axios.post(actionEndpoint);
                    if (successMessage) toast.success(successMessage);
                    await this.fetch();
                } catch (error) {
                    toast.error(this.errorMessage(error));
                    throw error;
                }
            },

            errorMessage(error) {
                if (error.response?.data?.message) {
                    return error.response.data.message;
                }
                const first = error.response?.data?.errors;
                if (first && typeof first === 'object') {
                    return Object.values(first).flat()[0] ?? 'Something went wrong.';
                }
                return 'Something went wrong.';
            },

            setPage(page) {
                this.page = page;
                this.fetch();
            },

            setSearch(search) {
                this.search = search;
                this.page = 1;
                this.fetch();
            },

            setFilter(key, value) {
                this.filters = { ...this.filters, [key]: value };
                this.page = 1;
                this.fetch();
            },

            resetFilters() {
                this.filters = {};
                this.page = 1;
                this.fetch();
            },

            async bulkAction(payload, successMessage) {
                const toast = useToastStore();
                try {
                    const { data } = await axios.post(`${endpoint}/bulk`, payload);
                    toast.success(successMessage ?? data.message ?? 'Done.');
                    await this.fetch();
                } catch (error) {
                    toast.error(this.errorMessage(error));
                    throw error;
                }
            },

            sort(field) {
                if (this.sortField === field) {
                    this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortField = field;
                    this.sortDir = 'asc';
                }
                this.fetch();
            },
        },
    });
}
