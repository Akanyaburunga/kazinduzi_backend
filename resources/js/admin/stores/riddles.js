import { useCrudStore } from './base.js';

export const useRiddlesStore = useCrudStore('riddles', '/admin/api/riddles', {
    initialSort: { field: 'created_at', dir: 'desc' },
    filters: { status: '', category_id: '', difficulty: '', trashed: '' },
});
