import { useCrudStore } from './base.js';

export const useJokesStore = useCrudStore('jokes', '/admin/api/jokes', {
    initialSort: { field: 'created_at', dir: 'desc' },
    filters: { status: '', category_id: '', trashed: '' },
});