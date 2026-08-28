<script setup>
import { ref, computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useAchievementsStore } from '../../stores/achievements.js';
import DataTable from '../../components/DataTable.vue';
import ConfirmDialog from '../../components/ConfirmDialog.vue';

const store = useAchievementsStore();
const { items, loading } = storeToRefs(store);

const showForm = ref(false);
const editing = ref(null);
const pendingDelete = ref(null);
const actionBusy = ref(false);
const syncing = ref(false);
const form = ref(emptyForm());

const metricLabels = {
    solved: 'Riddles solved',
    no_hint: 'No-hint solves',
    streak: 'Longest streak',
    category_master: 'Categories mastered',
    daily_champion: 'Daily champion days',
};

const columns = [
    { key: 'sort_order', label: '#' },
    { key: 'name', label: 'Name', sortable: true },
    { key: 'slug', label: 'Slug' },
    { key: 'category', label: 'Category' },
    { key: 'metric', label: 'Progress metric' },
    { key: 'threshold', label: 'Goal' },
    { key: 'is_active', label: 'Active' },
];

function emptyForm() {
    return {
        slug: '',
        name: '',
        description: '',
        category: 'solved',
        metric: 'solved',
        threshold: 1,
        icon: '',
        sort_order: 0,
        is_active: true,
    };
}

function metricLabel(metric) {
    return metricLabels[metric] ?? metric;
}

function openCreate() {
    editing.value = null;
    form.value = emptyForm();
    showForm.value = true;
}

function openEdit(row) {
    editing.value = row;
    form.value = {
        slug: row.slug,
        name: row.name,
        description: row.description,
        category: row.category,
        metric: row.metric,
        threshold: row.threshold,
        icon: row.icon ?? '',
        sort_order: row.sort_order ?? 0,
        is_active: row.is_active,
    };
    showForm.value = true;
}

async function submitForm() {
    try {
        await store.save(form.value, editing.value?.id);
        showForm.value = false;
    } catch {
        // handled
    }
}

async function confirmDelete() {
    actionBusy.value = true;
    try {
        await store.remove(pendingDelete.value.id);
    } catch {
        // handled
    } finally {
        actionBusy.value = false;
        pendingDelete.value = null;
    }
}

async function syncCatalogue() {
    syncing.value = true;
    try {
        await store.custom('/admin/api/achievements/sync', 'Badge catalogue synchronised.');
    } catch {
        // handled
    } finally {
        syncing.value = false;
    }
}

const deleteMessage = computed(() =>
    `Delete the badge "${pendingDelete.value?.name ?? ''}"? Unlocks already granted are preserved.`
);

onMounted(() => store.fetch());
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Badges</h1>
                <p class="mt-1 text-sm text-gray-500">Achievements players unlock as they progress through the game.</p>
            </div>
            <div class="flex items-center gap-3">
                <button
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-60"
                    :disabled="syncing"
                    @click="syncCatalogue"
                >
                    {{ syncing ? 'Syncing...' : 'Sync catalogue' }}
                </button>
                <button
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    @click="openCreate"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    New Badge
                </button>
            </div>
        </div>

        <DataTable
            :columns="columns"
            :items="items"
            :loading="loading"
            id-field="id"
            @sort="store.sort"
        >
            <template #cell-is_active="{ value }">
                <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                    :class="value ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'"
                >
                    {{ value ? 'Active' : 'Inactive' }}
                </span>
            </template>
            <template #cell-metric="{ value }">
                {{ metricLabel(value) }}
            </template>
            <template #actions="{ row }">
                <div class="flex justify-end gap-2">
                    <button class="text-sm text-indigo-600 hover:underline" @click="openEdit(row)">Edit</button>
                    <button class="text-sm text-red-600 hover:underline" @click="pendingDelete = row">Delete</button>
                </div>
            </template>
        </DataTable>

        <Teleport to="body">
            <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4 backdrop-blur-sm" @click.self="showForm = false">
                <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-900">{{ editing ? 'Edit Badge' : 'New Badge' }}</h3>
                    <form class="mt-4 space-y-4" @submit.prevent="submitForm">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input v-model="form.name" type="text" required
                                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Slug</label>
                                <input v-model="form.slug" type="text" required
                                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <input v-model="form.description" type="text" required
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Category</label>
                                <input v-model="form.category" type="text"
                                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Progress metric</label>
                                <select v-model="form.metric"
                                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="solved">Riddles solved</option>
                                    <option value="no_hint">No-hint solves</option>
                                    <option value="streak">Longest streak</option>
                                    <option value="category_master">Categories mastered</option>
                                    <option value="daily_champion">Daily champion days</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Goal (threshold)</label>
                                <input v-model.number="form.threshold" type="number" min="1" required
                                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Sort order</label>
                                <input v-model.number="form.sort_order" type="number" min="0"
                                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Icon</label>
                            <input v-model="form.icon" type="text"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div class="flex items-center gap-2">
                            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                            <label class="text-sm text-gray-700">Active</label>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button
                                type="button"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                                :disabled="store.saving"
                                @click="showForm = false"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                                :disabled="store.saving"
                            >
                                {{ store.saving ? 'Saving...' : editing ? 'Save changes' : 'Create badge' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <ConfirmDialog
            :open="pendingDelete !== null"
            title="Delete badge?"
            :message="deleteMessage"
            confirm-label="Delete"
            :busy="actionBusy"
            @confirm="confirmDelete"
            @cancel="pendingDelete = null"
        />
    </div>
</template>
