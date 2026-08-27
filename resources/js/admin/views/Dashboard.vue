<script setup>
import { ref, onMounted } from 'vue';
import axios from '../bootstrap.js';
import StatCard from '../components/StatCard.vue';

const stats = ref({
    total_riddles: 0,
    suspended_riddles: 0,
    total_categories: 0,
    total_attempts: 0,
    correct_attempts: 0,
    total_solves: 0,
    today_solves: 0,
    today_attempts: 0,
    active_players: 0,
    today_solvers: 0,
    top_riddles: [],
    difficulty_breakdown: [],
});

const loading = ref(true);

const difficultyTone = {
    easy: 'bg-emerald-100 text-emerald-700',
    medium: 'bg-amber-100 text-amber-700',
    hard: 'bg-rose-100 text-rose-700',
};

function maxDifficulty() {
    return Math.max(1, ...stats.value.difficulty_breakdown.map((d) => d.total));
}

const quickLinks = [
    { name: 'admin.riddles.index', label: 'Manage Riddles', description: 'Create, edit and moderate riddles.', icon: 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z' },
    { name: 'admin.categories.index', label: 'Manage Categories', description: 'Organise riddles by topic.', icon: 'M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z' },
];

onMounted(async () => {
    try {
        const { data } = await axios.get('/admin/api/dashboard');
        stats.value = data.data;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-sm text-gray-500">Overview of the Kazinduzi platform.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <StatCard label="Riddles" :value="loading ? '—' : stats.total_riddles" tone="indigo" icon="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
            <StatCard label="Categories" :value="loading ? '—' : stats.total_categories" tone="violet" icon="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z" />
            <StatCard label="Attempts" :value="loading ? '—' : stats.total_attempts" tone="sky" icon="M12 6v6h4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            <StatCard label="Total solves" :value="loading ? '—' : stats.total_solves" tone="emerald" icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            <StatCard label="Today's solves" :value="loading ? '—' : stats.today_solves" tone="green" icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            <StatCard label="Active players" :value="loading ? '—' : stats.active_players" tone="cyan" icon="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            <StatCard label="Suspended riddles" :value="loading ? '—' : stats.suspended_riddles" tone="rose" icon="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
                <h2 class="text-sm font-semibold text-gray-900">Top riddles by solves</h2>
                <p class="mt-0.5 text-xs text-gray-500">The five most-solved riddles right now.</p>
                <div v-if="stats.top_riddles.length === 0" class="py-8 text-center text-sm text-gray-400">No solves recorded yet.</div>
                <div v-else class="mt-3 divide-y divide-gray-100">
                    <router-link
                        v-for="r in stats.top_riddles"
                        :key="r.id"
                        :to="{ name: 'admin.riddles.show', params: { id: r.id } }"
                        class="flex items-center justify-between gap-3 py-3 transition hover:bg-gray-50"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-900">{{ r.question }}</p>
                            <p class="text-xs text-gray-500">{{ r.category ?? 'No category' }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-semibold text-emerald-600">{{ r.solved_count }} solved</p>
                            <p class="text-xs text-gray-400">{{ r.attempts_count }} attempts</p>
                        </div>
                    </router-link>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Riddles by difficulty</h2>
                <p class="mt-0.5 text-xs text-gray-500">Distribution across the library.</p>
                <div v-if="stats.difficulty_breakdown.length === 0" class="py-8 text-center text-sm text-gray-400">No riddles.</div>
                <div v-else class="mt-4 space-y-3">
                    <div v-for="d in stats.difficulty_breakdown" :key="d.difficulty">
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="capitalize font-medium text-gray-700">{{ d.difficulty }}</span>
                            <span class="text-xs font-medium text-gray-500">{{ d.total }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded bg-gray-100">
                            <div class="h-full rounded" :class="difficultyTone[d.difficulty] || 'bg-gray-400'" :style="{ width: (d.total / maxDifficulty()) * 100 + '%' }"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <router-link
                v-for="link in quickLinks"
                :key="link.name"
                :to="{ name: link.name }"
                class="group flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-200 hover:shadow-md"
            >
                <div class="rounded-lg bg-indigo-50 p-3">
                    <svg class="h-6 w-6 text-indigo-600 transition group-hover:text-indigo-700" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="link.icon" />
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-gray-900 group-hover:text-indigo-700">{{ link.label }}</p>
                    <p class="mt-0.5 text-sm text-gray-500">{{ link.description }}</p>
                </div>
                <svg class="h-5 w-5 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </router-link>
        </div>
    </div>
</template>

