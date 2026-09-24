import { useCrudStore } from './base.js';

export const useProverbsStore = useCrudStore('proverbs', '/admin/api/proverbs', {
    initialSort: { field: 'created_at', dir: 'desc' },
    filters: { status: '', category_id: '', difficulty: '', trashed: '' },
});