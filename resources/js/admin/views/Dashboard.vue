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
    by_mode: [],
    round_stats: {
        total_rounds: 0,
        active_rounds: 0,
        completed_rounds: 0,
        rounds_today: 0,
        avg_score: 0,
        score_by_level: [],
        rounds_by_mode: [],
        started_last_7d: 0,
        started_last_30d: 0,
    },
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
    { name: 'admin.proverbs.index', label: 'Manage Proverbs', description: 'Create, edit and moderate proverbs.', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
    { name: 'admin.jokes.index', label: 'Manage Jokes', description: 'Create, edit and moderate jokes.', icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
    { name: 'admin.categories.index', label: 'Manage Categories', description: 'Organise content by topic.', icon: 'M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z' },
    { name: 'admin.analytics.index', label: 'View Analytics', description: 'Performance, rounds and contributions.', icon: 'M3 3v18h18M8 17v-5M13 17V8M18 17v-3' },
];

const modeTone = {
    sokwe: 'bg-indigo-100 text-indigo-700',
    hera: 'bg-amber-100 text-amber-700',
    tuja: 'bg-rose-100 text-rose-700',
};

function maxLevelRounds() {
    return Math.max(1, ...stats.value.round_stats.score_by_level.map((l) => l.rounds));
}

function maxModeItems() {
    return Math.max(1, ...stats.value.by_mode.map((m) => m.items));
}

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

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Game modes</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Library size and solve activity per mode.</p>
                </div>
            </div>
            <div v-if="stats.by_mode.length" class="mt-4 grid gap-4 md:grid-cols-3">
                <div v-for="m in stats.by_mode" :key="m.mode" class="rounded-lg border border-gray-100 p-4">
                    <div class="flex items-center justify-between">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="modeTone[m.mode] || 'bg-gray-100 text-gray-700'">
                            {{ m.label }}
                        </span>
                        <span class="text-xs text-gray-400">{{ m.today_solves }} solved today</span>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                        <div>
                            <p class="text-lg font-bold text-gray-900">{{ m.items }}</p>
                            <p class="text-[10px] uppercase tracking-wide text-gray-400">Items</p>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-900">{{ m.attempts }}</p>
                            <p class="text-[10px] uppercase tracking-wide text-gray-400">Attempts</p>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-emerald-600">{{ m.solves }}</p>
                            <p class="text-[10px] uppercase tracking-wide text-gray-400">Solves</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Rounds of 10</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Volume, completion and average score across all modes.</p>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <StatCard label="Total rounds" :value="loading ? '—' : stats.round_stats.total_rounds" tone="indigo" icon="M12 6v6h4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    <StatCard label="Active" :value="loading ? '—' : stats.round_stats.active_rounds" tone="sky" icon="M13 10V3L4 14h7v7l9-11h-7z" />
                    <StatCard label="Completed" :value="loading ? '—' : stats.round_stats.completed_rounds" tone="emerald" icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    <StatCard label="Today" :value="loading ? '—' : stats.round_stats.rounds_today" tone="cyan" icon="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    <StatCard label="Avg score" :value="loading ? '—' : stats.round_stats.avg_score" tone="violet" icon="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    <StatCard label="7-day starts" :value="loading ? '—' : stats.round_stats.started_last_7d" tone="amber" icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </div>
                <div class="mt-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Score by level</h3>
                    <div v-if="stats.round_stats.score_by_level.length === 0" class="py-6 text-center text-sm text-gray-400">No rounds yet.</div>
                    <div v-else class="mt-2 space-y-2">
                        <div v-for="l in stats.round_stats.score_by_level" :key="l.level" class="flex items-center gap-3">
                            <span class="w-8 text-xs font-semibold text-gray-500">Level {{ l.level }}</span>
                            <div class="flex-1">
                                <div class="mb-0.5 flex items-center justify-between text-xs text-gray-500">
                                    <span>{{ l.rounds }} rounds · avg {{ l.avg_score }}</span>
                                    <span>{{ l.completed }} completed</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded bg-gray-100">
                                    <div class="h-full rounded bg-indigo-400" :style="{ width: (l.rounds / maxLevelRounds()) * 100 + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Rounds by mode</h2>
                <p class="mt-0.5 text-xs text-gray-500">Distribution of started rounds.</p>
                <div v-if="stats.round_stats.rounds_by_mode.length === 0" class="py-8 text-center text-sm text-gray-400">No rounds yet.</div>
                <div v-else class="mt-4 space-y-3">
                    <div v-for="m in stats.round_stats.rounds_by_mode" :key="m.mode" class="flex items-center justify-between text-sm">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="modeTone[m.mode] || 'bg-gray-100 text-gray-700'">
                            {{ m.mode.charAt(0).toUpperCase() + m.mode.slice(1) }}
                        </span>
                        <span class="font-semibold text-gray-800">{{ m.count }}</span>
                    </div>
                </div>
            </div>
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

