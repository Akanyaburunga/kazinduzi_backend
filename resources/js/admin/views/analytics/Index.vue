<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from '../../bootstrap.js';

const tab = ref('performance');
const days = ref(14);
const loading = ref(true);

const performance = ref({ by_category: [], by_type: [], by_difficulty: [], by_mode: [], category_by_mode: {} });
const players = ref([]);
const conversion = ref([]);
const rounds = ref({ daily_rounds: [], score_by_level: [], rounds_by_mode: [] });
const contributions = ref({ by_queue: {}, totals: { pending: 0, approved: 0, rejected: 0 } });

const tabs = [
    { key: 'performance', label: 'Performance' },
    { key: 'rounds', label: 'Rounds' },
    { key: 'contributions', label: 'Contributions' },
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

const maxRoundsDay = computed(() => Math.max(1, ...rounds.value.daily_rounds.map((d) => d.rounds)));

const maxLevelRounds = computed(() => Math.max(1, ...rounds.value.score_by_level.map((l) => l.rounds)));

const modeTone = {
    sokwe: 'bg-indigo-100 text-indigo-700',
    hera: 'bg-amber-100 text-amber-700',
    tuja: 'bg-rose-100 text-rose-700',
};

const queueLabels = {
    riddles: 'Ibisokozo',
    proverbs: 'Imigani',
    jokes: 'Utujajuro',
};

async function loadPerformance() {
    const { data } = await axios.get('/admin/api/analytics/performance');
    performance.value = data.data;
}

async function loadRounds() {
    const { data } = await axios.get('/admin/api/analytics/rounds', { params: { days: days.value } });
    rounds.value = data.data;
}

async function loadContributions() {
    const { data } = await axios.get('/admin/api/analytics/contributions');
    contributions.value = data.data;
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
        await Promise.all([loadPerformance(), loadRounds(), loadContributions(), loadSeries()]);
    } finally {
        loading.value = false;
    }
}

function switchDays() {
    loadSeries();
    loadRounds();
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
                    @change="switchDays"
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
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">By mode</h2>
                <p class="mt-0.5 text-xs text-gray-500">Solve-rate across the three game queues.</p>
                <div v-if="performance.by_mode.length === 0" class="py-8 text-center text-sm text-gray-400">No activity yet.</div>
                <div v-else class="mt-3 grid gap-4 sm:grid-cols-3">
                    <div v-for="m in performance.by_mode" :key="m.mode" class="rounded-lg border border-gray-100 p-4">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="modeTone[m.mode] || 'bg-gray-100 text-gray-700'">
                                {{ m.label }}
                            </span>
                            <span class="text-xs font-semibold" :class="successTone(m.success_rate)">{{ m.success_rate }}%</span>
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

            <div v-if="Object.keys(performance.category_by_mode).length" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Solve-rate by category and mode</h2>
                <p class="mt-0.5 text-xs text-gray-500">Where each mode performs best.</p>
                <div v-if="loading" class="py-8 text-center text-sm text-gray-400">Loading…</div>
                <div v-else class="mt-3 space-y-5">
                    <div v-for="(rows, mode) in performance.category_by_mode" :key="mode">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="modeTone[mode] || 'bg-gray-100 text-gray-700'">
                                {{ mode.charAt(0).toUpperCase() + mode.slice(1) }}
                            </span>
                        </h3>
                        <div v-if="rows.length === 0" class="py-4 text-center text-sm text-gray-400">No attempts recorded.</div>
                        <table v-else class="mt-2 w-full text-left text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in rows" :key="row.category_id ?? row.name">
                                    <td class="py-2 pr-3 font-medium text-gray-800">{{ row.name }}</td>
                                    <td class="py-2 pr-3 text-gray-600">{{ row.attempts }} attempts</td>
                                    <td class="py-2 pr-3 text-gray-600">{{ row.solves }} solves</td>
                                    <td class="py-2">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="successTone(row.success_rate)">
                                            {{ row.success_rate }}%
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

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

        <!-- Rounds -->
        <section v-else-if="tab === 'rounds'" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="card in [
                    { label: 'Total rounds', value: rounds.total_rounds, tone: 'text-indigo-700' },
                    { label: 'Completed', value: rounds.completed_rounds, tone: 'text-emerald-700' },
                    { label: 'Active', value: rounds.active_rounds, tone: 'text-sky-700' },
                    { label: 'Avg score', value: rounds.avg_score, tone: 'text-violet-700' },
                ]" :key="card.label" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ card.label }}</p>
                    <p class="mt-1 text-2xl font-bold" :class="card.tone">{{ card.value }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Rounds per day</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Started (and completed) rounds over the last {{ days }} days.</p>
                    </div>
                    <span class="text-xs text-gray-400">completion {{ rounds.completion_rate }}% · level-up {{ rounds.level_up_rate }}%</span>
                </div>
                <div v-if="loading" class="py-10 text-center text-sm text-gray-400">Loading…</div>
                <div v-else-if="rounds.daily_rounds.length === 0" class="py-10 text-center text-sm text-gray-400">No rounds in this period.</div>
                <template v-else>
                    <div class="mt-4 flex h-40 items-end gap-1">
                        <div v-for="(d, i) in rounds.daily_rounds" :key="i" class="flex flex-1 flex-col items-center gap-1" :title="`${d.day}: ${d.rounds} rounds`">
                            <div class="flex w-full items-end justify-center gap-0.5">
                                <div class="w-2 rounded-t bg-indigo-400" :style="{ height: (d.rounds / maxRoundsDay) * 140 + 'px', minHeight: '2px' }" :title="`${d.day}: ${d.rounds} started`"></div>
                                <div class="w-2 rounded-t bg-emerald-400" :style="{ height: (d.completed / maxRoundsDay) * 140 + 'px', minHeight: '2px' }" :title="`${d.day}: ${d.completed} completed`"></div>
                            </div>
                            <span class="hidden text-[10px] text-gray-400 sm:inline">{{ d.day.slice(5) }}</span>
                        </div>
                    </div>
                    <div class="mt-2 flex gap-4 text-xs text-gray-500">
                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-indigo-400"></span>Started</span>
                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-emerald-400"></span>Completed</span>
                    </div>
                </template>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold text-gray-900">Score by level</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Rounds, completion and average score per level.</p>
                    <div v-if="rounds.score_by_level.length === 0" class="py-8 text-center text-sm text-gray-400">No rounds yet.</div>
                    <div v-else class="mt-4 space-y-3">
                        <div v-for="l in rounds.score_by_level" :key="l.level">
                            <div class="mb-1 flex items-center justify-between text-sm">
                                <span class="font-medium text-gray-700">Level {{ l.level }}</span>
                                <span class="text-xs text-gray-500">{{ l.rounds }} rounds · avg {{ l.avg_score }} · {{ l.completion_rate }}% completed</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded bg-gray-100">
                                <div class="h-full rounded bg-indigo-400" :style="{ width: (l.rounds / maxLevelRounds) * 100 + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold text-gray-900">Rounds by mode</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Distribution across sokwe, hera and tuja.</p>
                    <div v-if="rounds.rounds_by_mode.length === 0" class="py-8 text-center text-sm text-gray-400">No rounds yet.</div>
                    <div v-else class="mt-3 space-y-2">
                        <div v-for="m in rounds.rounds_by_mode" :key="m.mode" class="flex items-center justify-between rounded-lg border border-gray-100 px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="modeTone[m.mode] || 'bg-gray-100 text-gray-700'">
                                {{ m.mode.charAt(0).toUpperCase() + m.mode.slice(1) }}
                            </span>
                            <span class="font-semibold text-gray-800">{{ m.count }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contributions -->
        <section v-else-if="tab === 'contributions'" class="space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Moderation funnel</h2>
                <p class="mt-0.5 text-xs text-gray-500">Pending, approved and rejected contributions per queue.</p>
                <div v-if="loading" class="py-8 text-center text-sm text-gray-400">Loading…</div>
                <div v-else-if="Object.keys(contributions.by_queue).length === 0" class="py-8 text-center text-sm text-gray-400">No contributions yet.</div>
                <table v-else class="mt-3 w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase text-gray-400">
                            <th class="py-2 pr-3 font-medium">Queue</th>
                            <th class="py-2 pr-3 font-medium">Pending</th>
                            <th class="py-2 pr-3 font-medium">Approved</th>
                            <th class="py-2 font-medium">Rejected</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="(counts, queue) in contributions.by_queue" :key="queue">
                            <td class="py-2.5 pr-3 font-medium text-gray-800">{{ queueLabels[queue] ?? queue }}</td>
                            <td class="py-2.5 pr-3">
                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">{{ counts.pending }}</span>
                            </td>
                            <td class="py-2.5 pr-3">
                                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">{{ counts.approved }}</span>
                            </td>
                            <td class="py-2.5">
                                <span class="inline-flex rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700">{{ counts.rejected }}</span>
                            </td>
                        </tr>
                        <tr class="border-t-2 border-gray-200 font-semibold">
                            <td class="py-2.5 pr-3 text-gray-900">Totals</td>
                            <td class="py-2.5 pr-3 text-amber-700">{{ contributions.totals.pending }}</td>
                            <td class="py-2.5 pr-3 text-emerald-700">{{ contributions.totals.approved }}</td>
                            <td class="py-2.5 text-rose-700">{{ contributions.totals.rejected }}</td>
                        </tr>
                    </tbody>
                </table>
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
