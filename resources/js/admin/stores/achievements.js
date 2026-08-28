import { useCrudStore } from './base.js';

export const useAchievementsStore = useCrudStore('achievements', '/admin/api/achievements', {
    initialSort: { field: 'sort_order', dir: 'asc' },
    actions: {},
});
