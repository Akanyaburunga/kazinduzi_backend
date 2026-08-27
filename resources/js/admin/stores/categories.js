import { useCrudStore } from './base.js';

export const useCategoriesStore = useCrudStore('categories', '/admin/api/categories', {
    initialSort: { field: 'name', dir: 'asc' },
});
