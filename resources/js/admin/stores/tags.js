import { useCrudStore } from './base.js';

export const useTagsStore = useCrudStore('tags', '/admin/api/tags', {
    initialSort: { field: 'name', dir: 'asc' },
});
