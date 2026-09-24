<script setup>
import { ref, computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useRouter } from 'vue-router';
import axios from '../../bootstrap.js';
import { useToastStore } from '../../stores/toast.js';
import { useProverbsStore } from '../../stores/proverbs.js';
import { useCategoriesStore } from '../../stores/categories.js';
import DataTable from '../../components/DataTable.vue';
import ConfirmDialog from '../../components/ConfirmDialog.vue';
import ProverbForm from './ProverbForm.vue';

const router = useRouter();
const toast = useToastStore();
const store = useProverbsStore();
const categoriesStore = useCategoriesStore();

const { items, meta, loading, search, sortField, sortDir, filters } = storeToRefs(store);
const { items: categories } = storeToRefs(categoriesStore);

const showForm = ref(false);
const editing = ref(null);
const serverErrors = ref({});
const duplicate = ref(null);
const pendingDelete = ref(null);
const pendingSuspend = ref(null);
const pendingUnsuspend = ref(null);
const pendingRestore = ref(null);
const selected = ref([]);
const pendingBulkDelete = ref(false);
const pendingBulkSuspend = ref(false);
const pendingBulkUnsuspend = ref(false);
const pendingCategoryChange = ref(false);
const bulkCategoryId = ref('');
const bulkBusy = ref(false);
const actionBusy = ref(false);
const exporting = ref(false);

const columns = [
    { key: 'question', label: 'Question', sortable: true },
    { key: 'answer', label: 'Answer', sortable: true },
    { key: 'difficulty', label: 'Difficulty', sortable: true },
    { key: 'status', label: 'Status' },
    { key: 'attempts_count', label: 'Attempts', sortable: true },
    { key: 'solved_count', label: 'Solved', sortable: true },
    { key: 'success_rate', label: 'Success %' },
];

const selectedCount = computed(() => selected.value.length);

const filterOptions = [
    { value: '', label: 'All statuses' },
    { value: 'active', label: 'Active only' },
    { value: 'suspended', label: 'Suspended only' },
];

const trashedOptions = [
    { value: '', label: 'Trash: Off' },
    { value: '1', label: 'Trash: On' },
];

function setFilter(key, value) {
    selected.value = [];
    store.setFilter(key, value);
}

function clearFilters() {
    selected.value = [];
    store.resetFilters();
}

function truncate(value) {
    const text = value ?? '';
    return text.length > 15 ? text.slice(0, 15) + '…' : text;
}

async function exportCsv() {
    exporting.value = true;
    try {
        const response = await axios.get('/admin/api/proverbs/export', { params: store.buildParams() });
        const disposition = response.headers['content-disposition'] || '';
        const match = disposition.match(/filename="?([^";]+)"?/);
        const filename = match ? match[1] : `proverbs-${new Date().toISOString().slice(0, 10)}.csv`;
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        window.URL.revokeObjectURL(url);
    } catch {
        toast.error('Could not export proverbs.');
    } finally {
        exporting.value = false;
    }
}

async function confirmRestore() {
    actionBusy.value = true;
    try {
        await store.custom(`/admin/api/proverbs/${pendingRestore.value.id}/restore`, 'Proverb restored.');
        await store.setFilter('trashed', filters.value.trashed || '1');
    } catch {
        // handled
    } finally {
        actionBusy.value = false;
        pendingRestore.value = null;
    }
}

async function runBulk(action) {
    bulkBusy.value = true;
    const payload = { ids: selected.value, action };
    if (action === 'change_category') {
        payload.category_id = Number(bulkCategoryId.value);
    }
    try {
        await store.bulkAction(payload);
        selected.value = [];
    } catch {
        // handled
    } finally {
        bulkBusy.value = false;
        pendingBulkDelete.value = false;
        pendingBulkSuspend.value = false;
        pendingBulkUnsuspend.value = false;
        pendingCategoryChange.value = false;
        bulkCategoryId.value = '';
    }
}

function openCreate() {
    editing.value = null;
    serverErrors.value = {};
    duplicate.value = null;
    showForm.value = true;
}

function openEdit(row) {
    editing.value = row;
    serverErrors.value = {};
    duplicate.value = null;
    showForm.value = true;
}

async function submitForm(payload) {
    serverErrors.value = {};
    duplicate.value = null;
    try {
        await store.save(payload, editing.value?.id);
        showForm.value = false;
    } catch (error) {
        serverErrors.value = error.response?.data?.errors ?? {};
        duplicate.value = error.response?.data?.duplicate ?? null;
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

async function confirmSuspend() {
    actionBusy.value = true;
    try {
        await store.custom(`/admin/api/proverbs/${pendingSuspend.value.id}/suspend`, 'Proverb suspended.');
    } catch {
        // handled
    } finally {
        actionBusy.value = false;
        pendingSuspend.value = null;
    }
}

async function confirmUnsuspend() {
    actionBusy.value = true;
    try {
        await store.custom(`/admin/api/proverbs/${pendingUnsuspend.value.id}/unsuspend`, 'Proverb unsuspended.');
    } catch {
        // handled
    } finally {
        actionBusy.value = false;
        pendingUnsuspend.value = null;
    }
}

onMounted(async () => {
    await Promise.all([store.fetch(), categoriesStore.fetch()]);
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Proverbs</h1>
                <p class="mt-1 text-sm text-gray-500">Create and moderate proverbs (Heraheza) on the platform.</p>
            </div>
            <button
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="openCreate"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                New Proverb
            </button>
        </div>

        <DataTable
            :columns="columns"
            :items="items"
            :loading="loading"
            :meta="meta"
            :searchable="true"
            :search="search"
            :sort-field="sortField"
            :sort-dir="sortDir"
            id-field="id"
            :selectable="true"
            :selected="selected"
            @update:selected="(value) => (selected = value)"
            @update:search="store.setSearch"
            @sort="store.sort"
            @page="store.setPage"
        >
            <template #toolbar>
                <div class="flex flex-wrap items-center gap-2">
                    <select
                        :value="filters.status"
                        class="rounded-lg border-gray-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @change="setFilter('status', $event.target.value)"
                    >
                        <option v-for="opt in filterOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <select
                        :value="filters.category_id"
                        class="rounded-lg border-gray-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @change="setFilter('category_id', $event.target.value)"
                    >
                        <option value="">All categories</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                    <select
                        :value="filters.difficulty"
                        class="rounded-lg border-gray-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @change="setFilter('difficulty', $event.target.value)"
                    >
                        <option value="">All difficulties</option>
                        <option value="easy">Easy</option>
                        <option value="medium">Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                    <select
                        :value="filters.trashed"
                        class="rounded-lg border-gray-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @change="setFilter('trashed', $event.target.value)"
                    >
                        <option v-for="opt in trashedOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <button
                        v-if="Object.values(filters).some((v) => v !== '' && v !== undefined)"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                        @click="clearFilters"
                    >
                        Clear
                    </button>
                    <button
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50"
                        :disabled="exporting"
                        @click="exportCsv"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0 0l-4-4m4 4l4-4" />
                        </svg>
                        {{ exporting ? 'Exporting...' : 'Export CSV' }}
                    </button>
                </div>
            </template>
            <template #cell-question="{ row }">
                <span class="whitespace-nowrap" :title="row.question">{{ truncate(row.question) }}</span>
            </template>
            <template #cell-answer="{ row }">
                <span class="whitespace-nowrap" :title="row.answer">{{ truncate(row.answer) }}</span>
            </template>
            <template #cell-difficulty="{ row }">
                <span
                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="{
                        'bg-emerald-100 text-emerald-700': row.difficulty === 'easy',
                        'bg-amber-100 text-amber-700': row.difficulty === 'medium',
                        'bg-rose-100 text-rose-700': row.difficulty === 'hard',
                    }"
                >
                    {{ row.difficulty ? row.difficulty.charAt(0).toUpperCase() + row.difficulty.slice(1) : '—' }}
                </span>
            </template>
            <template #cell-status="{ row }">
                <span
                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="row.is_suspended ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'"
                >
                    {{ row.is_suspended ? 'Suspended' : 'Active' }}
                </span>
            </template>
            <template #actions="{ row }">
                <div class="flex justify-end gap-1">
                    <button
                        class="rounded-lg p-1.5 text-indigo-600 transition hover:bg-indigo-50"
                        title="Edit"
                        @click="openEdit(row)"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>
                    <button
                        class="rounded-lg p-1.5 text-sky-600 transition hover:bg-sky-50"
                        title="Analytics"
                        @click="router.push({ name: 'admin.proverbs.show', params: { id: row.id } })"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </button>
                    <button
                        v-if="row.deleted_at"
                        class="rounded-lg p-1.5 text-teal-600 transition hover:bg-teal-50"
                        title="Restore"
                        @click="pendingRestore = row"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                    <template v-else>
                        <button
                            v-if="row.is_suspended"
                            class="rounded-lg p-1.5 text-green-600 transition hover:bg-green-50"
                            title="Unsuspend"
                            @click="pendingUnsuspend = row"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                        <button v-else class="rounded-lg p-1.5 text-amber-600 transition hover:bg-amber-50" title="Suspend" @click="pendingSuspend = row">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                        </button>
                        <button class="rounded-lg p-1.5 text-red-600 transition hover:bg-red-50" title="Delete" @click="pendingDelete = row">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </template>
                </div>
            </template>
        </DataTable>

        <div v-if="selectedCount > 0" class="flex flex-wrap items-center gap-3 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3">
            <span class="text-sm font-medium text-indigo-700">{{ selectedCount }} selected</span>
            <button
                class="rounded-lg bg-amber-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-amber-700"
                @click="pendingBulkSuspend = true"
            >
                Suspend
            </button>
            <button
                class="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-green-700"
                @click="pendingBulkUnsuspend = true"
            >
                Unsuspend
            </button>
            <select
                v-model="bulkCategoryId"
                class="rounded-lg border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option value="">Move to category...</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
            <button
                class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-indigo-700"
                :disabled="!bulkCategoryId"
                @click="pendingCategoryChange = true"
            >
                Move
            </button>
            <button
                class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-red-700"
                @click="pendingBulkDelete = true"
            >
                Delete
            </button>
            <button class="ml-auto text-sm text-gray-500 hover:underline" @click="selected = []">Clear</button>
        </div>

        <ProverbForm
            :open="showForm"
            :proverb="editing"
            :categories="categories"
            :saving="store.saving"
            :server-errors="serverErrors"
            :duplicate="duplicate"
            @close="showForm = false"
            @submit="submitForm"
        />

        <ConfirmDialog
            :open="pendingDelete !== null"
            title="Delete proverb?"
            :message="'Delete the proverb: &quot;' + (pendingDelete?.question ?? '') + '&quot;?'"
            confirm-label="Delete"
            :busy="actionBusy"
            @confirm="confirmDelete"
            @cancel="pendingDelete = null"
        />
        <ConfirmDialog
            :open="pendingSuspend !== null"
            title="Suspend proverb?"
            message="This will hide the proverb from players. You can unsuspend it later."
            :busy="actionBusy"
            @confirm="confirmSuspend"
            @cancel="pendingSuspend = null"
        />
        <ConfirmDialog
            :open="pendingUnsuspend !== null"
            title="Unsuspend proverb?"
            message="This will make the proverb visible to players again."
            confirm-label="Unsuspend"
            :busy="actionBusy"
            @confirm="confirmUnsuspend"
            @cancel="pendingUnsuspend = null"
        />
        <ConfirmDialog
            :open="pendingRestore !== null"
            title="Restore proverb?"
            :message="'Restore the proverb: &quot;' + (pendingRestore?.question ?? '') + '&quot;?'"
            confirm-label="Restore"
            :busy="actionBusy"
            @confirm="confirmRestore"
            @cancel="pendingRestore = null"
        />
        <ConfirmDialog
            :open="pendingBulkDelete"
            title="Delete selected proverbs?"
            :message="`Delete ${selectedCount} selected proverb(s)? This can be restored later from trash.`"
            confirm-label="Delete"
            :busy="bulkBusy"
            @confirm="runBulk('delete')"
            @cancel="pendingBulkDelete = false"
        />
        <ConfirmDialog
            :open="pendingBulkSuspend"
            title="Suspend selected proverbs?"
            :message="`Suspend ${selectedCount} selected proverb(s)? They will be hidden from players.`"
            confirm-label="Suspend"
            :busy="bulkBusy"
            @confirm="runBulk('suspend')"
            @cancel="pendingBulkSuspend = false"
        />
        <ConfirmDialog
            :open="pendingBulkUnsuspend"
            title="Unsuspend selected proverbs?"
            :message="`Unsuspend ${selectedCount} selected proverb(s)?`"
            confirm-label="Unsuspend"
            :busy="bulkBusy"
            @confirm="runBulk('unsuspend')"
            @cancel="pendingBulkUnsuspend = false"
        />
        <ConfirmDialog
            :open="pendingCategoryChange"
            title="Move proverbs to category?"
            :message="`Move ${selectedCount} selected proverb(s) to the chosen category?`"
            confirm-label="Move"
            :busy="bulkBusy"
            @confirm="runBulk('change_category')"
            @cancel="pendingCategoryChange = false"
        />
    </div>
</template>