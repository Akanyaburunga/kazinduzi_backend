<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from '../../bootstrap.js';

const tab = ref('performance');
const days = ref(14);
const loading = ref(true);

const performance = ref({ by_category: [], by_type: [], by_difficulty: [] });
const players = ref([]);
const conversion = ref([]);

const tabs = [
    { key: 'performance', label: 'Performance' },
    { key: 'players', label: 'Players' },
    { key: 'conversion', label: 'Daily conversion' },
];

const successTone = (rate) => {
    if (rate >= 60) return 'bg-emerald-100 text-emerald-700';
    if (rate >= 30) return 'bg-amber-100 text-amber-700';
    return 'bg-rose-100 text-rose-700';
};

const conversionTone = successTone;

const maxDaily = computed(() => Math.max(1, ...Object.values(players.value)));

async function loadPerformance() {
    const { data } = await axios.get('/admin/api/analytics/performance');
    performance.value = data.data;
}

async function loadSeries() {
    const { data: p } = await axios.get('/admin/api/analytics/players', { params: { days: days.value } });
    players.value = p.data.daily_active_players;

    const { data: c } = await axios.get('/admin/api/analytics/daily-conversion', { params: { days: days.value } });
    conversion.value = c.data.daily_conversion;
}

async function loadAll() {
    loading.value = true;
    try {
        await loadPerformance();
        await loadSeries();
    } finally {
        loading.value = false;
    }
}

function switchTab(key) {
    tab.value = key;
}

onMounted(loadAll);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Analytics</h1>
                <p class="mt-1 text-sm text-gray-500">Performance, players and daily-challenge conversion.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-gray-500">Days</span>
                <select
                    v-model="days"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    @change="loadSeries"
                >
                    <option :value="7">7 days</option>
                    <option :value="14">14 days</option>
                    <option :value="30">30 days</option>
                </select>
            </div>
        </div>

        <div class="flex flex-wrap gap-1 rounded-lg bg-gray-100 p-1 sm:w-fit">
            <button
                v-for="t in tabs"
                :key="t.key"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                :class="tab === t.key ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-600 hover:bg-gray-200'"
                @click="switchTab(t.key)"
            >
                {{ t.label }}
            </button>
        </div>

        <!-- Performance -->
        <section v-if="tab === 'performance'" class="space-y-6">
            <div v-for="(group, key) in [
                { key: 'by_category', label: 'By category', valueKey: 'name', headers: ['Category', 'Riddles', 'Attempts', 'Solves', 'Success'] },
                { key: 'by_type', label: 'By type', valueKey: 'type', headers: ['Type', 'Attempts', 'Solves', 'Success'] },
                { key: 'by_difficulty', label: 'By difficulty', valueKey: 'difficulty', headers: ['Difficulty', 'Attempts', 'Solves', 'Success'] },
            ]" :key="key">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-900">{{ group.label }}</h2>
                        <span class="text-xs text-gray-400">success rate = solves / attempts</span>
                    </div>
                    <div v-if="performance[group.key].length === 0" class="py-8 text-center text-sm text-gray-400">No activity yet.</div>
                    <table v-else class="mt-3 w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-xs uppercase text-gray-400">
                                <th v-for="h in group.headers" :key="h" class="py-2 pr-3 font-medium">{{ h }}</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in performance[group.key]" :key="row[group.valueKey] ?? row.name">
                                <td class="py-2.5 pr-3 font-medium capitalize text-gray-800">{{ row.name ?? row[group.valueKey] }}</td>
                                <td v-if="group.valueKey === 'name'" class="py-2.5 pr-3 text-gray-600">{{ row.riddles }}</td>
                                <td class="py-2.5 pr-3 text-gray-600">{{ row.attempts }}</td>
                                <td class="py-2.5 pr-3 text-gray-600">{{ row.solves }}</td>
                                <td class="py-2.5">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="successTone(row.success_rate)">
                                        {{ row.success_rate }}%
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Players -->
        <section v-else-if="tab === 'players'" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Daily active players</h2>
            <p class="mt-0.5 text-xs text-gray-500">Distinct users attempting riddles per day.</p>
            <div v-if="loading" class="py-10 text-center text-sm text-gray-400">Loading…</div>
            <div v-else class="mt-4 flex h-40 items-end gap-1">
                <template v-for="(count, day) in players" :key="day">
                    <div class="flex flex-1 flex-col items-center gap-1" :title="`${day}: ${count}`">
                        <div class="w-full rounded-t bg-indigo-500 transition-colors hover:bg-indigo-600" :style="{ height: (count / maxDaily) * 140 + 'px', minHeight: '2px' }"></div>
                        <span class="hidden text-[10px] text-gray-400 sm:inline">{{ day.slice(5) }}</span>
                    </div>
                </template>
            </div>
        </section>

        <!-- Daily conversion -->
        <section v-else class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Daily challenge conversion</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Active users converted into daily solvers.</p>
                </div>
                <span v-if="conversion.length" class="text-xs text-gray-400">last {{ days }} days</span>
            </div>
            <div v-if="conversion.length === 0" class="py-8 text-center text-sm text-gray-400">No activity yet.</div>
            <table v-else class="mt-3 w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-xs uppercase text-gray-400">
                        <th class="py-2 pr-3 font-medium">Day</th>
                        <th class="py-2 pr-3 font-medium">Active users</th>
                        <th class="py-2 pr-3 font-medium">Solvers</th>
                        <th class="py-2 font-medium">Conversion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in conversion" :key="row.day">
                        <td class="py-2.5 pr-3 text-gray-800">{{ row.day }}</td>
                        <td class="py-2.5 pr-3 text-gray-600">{{ row.active_users }}</td>
                        <td class="py-2.5 pr-3 text-gray-600">{{ row.solvers }}</td>
                        <td class="py-2.5">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="conversionTone(row.conversion_rate)">
                                {{ row.conversion_rate }}%
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
