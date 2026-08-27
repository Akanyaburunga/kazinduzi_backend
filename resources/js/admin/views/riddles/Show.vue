<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from '../../bootstrap.js';
import StatCard from '../../components/StatCard.vue';

const route = useRoute();
const router = useRouter();

const stats = ref(null);
const loading = ref(true);
const error = ref(null);

const id = computed(() => route.params.id);

const maxDay = computed(() => {
    const rows = Object.values(stats.value?.attempts_by_day ?? {});
    return rows.reduce((m, r) => Math.max(m, r.attempts), 0);
});

const maxWrong = computed(() => {
    const rows = stats.value?.wrong_answers ?? [];
    return rows.reduce((m, r) => Math.max(m, r.total), 0);
});

const dayEntries = computed(() =>
    Object.entries(stats.value?.attempts_by_day ?? {})
        .map(([day, v]) => ({ day, ...v }))
        .sort((a, b) => (a.day < b.day ? -1 : 1))
);

function barHeight(value, max) {
    if (!max) return 2;
    return Math.max(2, Math.round((value / max) * 96));
}

onMounted(async () => {
    try {
        const { data } = await axios.get(`/admin/api/riddles/${id.value}/stats`);
        stats.value = data.data;
    } catch {
        error.value = true;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <button class="mb-2 text-sm text-indigo-600 hover:underline" @click="router.push({ name: 'admin.riddles.index' })">
                    ← Back to riddles
                </button>
                <h1 class="text-2xl font-bold text-gray-900">Riddle analytics</h1>
                <p v-if="stats?.riddle" class="mt-1 text-sm text-gray-500">{{ stats.riddle.question }}</p>
            </div>
        </div>

        <div v-if="loading" class="py-16 text-center text-gray-400">Loading...</div>
        <div v-else-if="error" class="py-16 text-center text-red-500">Could not load analytics.</div>

        <template v-else>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Attempts" :value="stats.attempts_total" tone="sky" icon="M12 6v6h4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                <StatCard label="Solved" :value="stats.solved_count" tone="emerald" icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                <StatCard label="Success rate" :value="stats.success_rate + '%'" tone="indigo" icon="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                <StatCard label="Report window" :value="stats.report_days + ' days'" tone="violet" icon="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold text-gray-900">Attempts over the last {{ stats.report_days }} days</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Correct vs total attempts per day.</p>
                    <div v-if="dayEntries.length === 0" class="py-10 text-center text-sm text-gray-400">No attempts in this period.</div>
                    <div v-else class="mt-4 flex h-40 items-end gap-2">
                        <div v-for="(d, i) in dayEntries" :key="i" class="flex flex-1 flex-col items-center gap-1">
                            <div class="flex w-full items-end justify-center gap-0.5">
                                <div
                                    class="w-2 rounded-t bg-sky-400"
                                    :style="{ height: barHeight(d.attempts, maxDay) + 'px' }"
                                    :title="d.day + ': ' + d.attempts + ' attempts'"
                                ></div>
                                <div
                                    class="w-2 rounded-t bg-emerald-400"
                                    :style="{ height: barHeight(d.correct, maxDay) + 'px' }"
                                    :title="d.day + ': ' + d.correct + ' solved'"
                                ></div>
                            </div>
                            <span class="text-[10px] text-gray-400">{{ d.day.slice(5) }}</span>
                        </div>
                    </div>
                    <div class="mt-2 flex gap-4 text-xs text-gray-500">
                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-sky-400"></span>Attempts</span>
                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-emerald-400"></span>Solved</span>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold text-gray-900">Most common wrong answers</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Submitted answers that were incorrect.</p>
                    <div v-if="stats.wrong_answers.length === 0" class="py-10 text-center text-sm text-gray-400">No wrong answers recorded.</div>
                    <div v-else class="mt-4 space-y-2">
                        <div v-for="(w, i) in stats.wrong_answers" :key="i" class="flex items-center gap-3">
                            <span class="w-5 text-xs font-semibold text-gray-400">{{ i + 1 }}</span>
                            <div class="flex-1">
                                <div class="mb-0.5 flex items-center justify-between">
                                    <span class="truncate text-sm text-gray-700">{{ w.answer }}</span>
                                    <span class="text-xs font-medium text-gray-400">{{ w.total }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded bg-gray-100">
                                    <div class="h-full rounded bg-rose-400" :style="{ width: (w.total / maxWrong) * 100 + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
