<script setup>
import { ref, computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useRouter } from 'vue-router';
import axios from '../../bootstrap.js';
import { useToastStore } from '../../stores/toast.js';
import { useRiddlesStore } from '../../stores/riddles.js';
import { useCategoriesStore } from '../../stores/categories.js';
import { useTagsStore } from '../../stores/tags.js';
import { RIDDLE_TYPES } from '../../riddleTypes.js';
import DataTable from '../../components/DataTable.vue';
import ConfirmDialog from '../../components/ConfirmDialog.vue';
import RiddleForm from './RiddleForm.vue';

const router = useRouter();
const toast = useToastStore();
const store = useRiddlesStore();
const categoriesStore = useCategoriesStore();
const tagsStore = useTagsStore();

const { items, meta, loading, search, sortField, sortDir, filters } = storeToRefs(store);
const { items: categories } = storeToRefs(categoriesStore);
const { items: tags } = storeToRefs(tagsStore);

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
    { key: 'riddle_type', label: 'Type' },
    { key: 'category', label: 'Category' },
    { key: 'tags', label: 'Tags' },
    { key: 'source', label: 'Source' },
    { key: 'suspended_reason', label: 'Reason' },
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

const deleteMessage = computed(() =>
    `Delete the riddle: "${pendingDelete.value?.question ?? ''}"?`
);

const bulkDeleteMessage = computed(() =>
    `Delete ${selectedCount.value} selected riddle(s)? This can be restored later from trash.`
);

const restoreMessage = computed(() =>
    `Restore the riddle: "${pendingRestore.value?.question ?? ''}"?`
);

function setFilter(key, value) {
    selected.value = [];
    store.setFilter(key, value);
}

function clearFilters() {
    selected.value = [];
    store.resetFilters();
}

function typeLabel(value) {
    return RIDDLE_TYPES.find((t) => t.value === value)?.label ?? (value ? value.replace(/_/g, ' ') : '—');
}

async function exportCsv() {
    exporting.value = true;
    try {
        const response = await axios.get('/admin/api/riddles/export', { params: store.buildParams() });
        const disposition = response.headers['content-disposition'] || '';
        const match = disposition.match(/filename="?([^";]+)"?/);
        const filename = match ? match[1] : `riddles-${new Date().toISOString().slice(0, 10)}.csv`;
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        window.URL.revokeObjectURL(url);
    } catch {
        toast.error('Could not export riddles.');
    } finally {
        exporting.value = false;
    }
}

async function confirmRestore() {
    actionBusy.value = true;
    try {
        await store.custom(`/admin/api/riddles/${pendingRestore.value.id}/restore`, 'Riddle restored.');
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

function closeForm() {
    serverErrors.value = {};
    duplicate.value = null;
    showForm.value = false;
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
        await store.custom(`/admin/api/riddles/${pendingSuspend.value.id}/suspend`, 'Riddle suspended.');
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
        await store.custom(`/admin/api/riddles/${pendingUnsuspend.value.id}/unsuspend`, 'Riddle unsuspended.');
    } catch {
        // handled
    } finally {
        actionBusy.value = false;
        pendingUnsuspend.value = null;
    }
}

onMounted(async () => {
    await Promise.all([store.fetch(), categoriesStore.fetch(), tagsStore.fetch()]);
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Riddles</h1>
                <p class="mt-1 text-sm text-gray-500">Create and moderate riddles on the platform.</p>
            </div>
            <button
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="openCreate"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                New Riddle
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
                        :value="filters.type"
                        class="rounded-lg border-gray-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @change="setFilter('type', $event.target.value)"
                    >
                        <option value="">All types</option>
                        <option v-for="t in RIDDLE_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                    <select
                        :value="filters.tag_id"
                        class="rounded-lg border-gray-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @change="setFilter('tag_id', $event.target.value)"
                    >
                        <option value="">All tags</option>
                        <option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
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
            <template #cell-category="{ row }">
                {{ row.category?.name ?? '—' }}
            </template>
            <template #cell-suspended_reason="{ row }">
                <span class="text-xs text-gray-500">{{ row.suspended_reason ?? '—' }}</span>
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
            <template #cell-source="{ row }">
                <span class="text-xs text-gray-500">{{ row.source ?? '—' }}</span>
            </template>
            <template #cell-riddle_type="{ row }">
                <span class="text-xs text-gray-600">{{ typeLabel(row.riddle_type) }}</span>
            </template>
            <template #cell-tags="{ row }">
                <div class="flex flex-wrap gap-1">
                    <template v-if="row.tags?.length">
                        <span
                            v-for="tag in row.tags.slice(0, 2)"
                            :key="tag.id"
                            class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700"
                        >
                            {{ tag.name }}
                        </span>
                        <span v-if="row.tags.length > 2" class="text-xs text-gray-400">+{{ row.tags.length - 2 }}</span>
                    </template>
                    <span v-else class="text-xs text-gray-400">—</span>
                </div>
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
                <div class="flex justify-end gap-2">
                    <button class="text-sm text-indigo-600 hover:underline" @click="openEdit(row)">Edit</button>
                    <button class="text-sm text-sky-600 hover:underline" @click="router.push({ name: 'admin.riddles.show', params: { id: row.id } })">Analytics</button>
                    <button
                        v-if="row.deleted_at"
                        class="text-sm text-teal-600 hover:underline"
                        @click="pendingRestore = row"
                    >
                        Restore
                    </button>
                    <template v-else>
                        <button
                            v-if="row.is_suspended"
                            class="text-sm text-green-600 hover:underline"
                            @click="pendingUnsuspend = row"
                        >
                            Unsuspend
                        </button>
                        <button v-else class="text-sm text-amber-600 hover:underline" @click="pendingSuspend = row">Suspend</button>
                        <button class="text-sm text-red-600 hover:underline" @click="pendingDelete = row">Delete</button>
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

        <RiddleForm
            :open="showForm"
            :riddle="editing"
            :categories="categories"
            :saving="store.saving"
            :server-errors="serverErrors"
            :duplicate="duplicate"
            @close="closeForm"
            @submit="submitForm"
        />

        <ConfirmDialog
            :open="pendingDelete !== null"
            title="Delete riddle?"
            :message="deleteMessage"
            confirm-label="Delete"
            :busy="actionBusy"
            @confirm="confirmDelete"
            @cancel="pendingDelete = null"
        />

        <ConfirmDialog
            :open="pendingSuspend !== null"
            title="Suspend riddle?"
            message="This will hide the riddle from players. You can unsuspend it later."
            :busy="actionBusy"
            @confirm="confirmSuspend"
            @cancel="pendingSuspend = null"
        />

        <ConfirmDialog
            :open="pendingUnsuspend !== null"
            title="Unsuspend riddle?"
            message="This will make the riddle visible to players again."
            confirm-label="Unsuspend"
            :busy="actionBusy"
            @confirm="confirmUnsuspend"
            @cancel="pendingUnsuspend = null"
        />

        <ConfirmDialog
            :open="pendingRestore !== null"
            title="Restore riddle?"
            :message="restoreMessage"
            confirm-label="Restore"
            :busy="actionBusy"
            @confirm="confirmRestore"
            @cancel="pendingRestore = null"
        />

        <ConfirmDialog
            :open="pendingBulkDelete"
            title="Delete selected riddles?"
            :message="bulkDeleteMessage"
            confirm-label="Delete"
            :busy="bulkBusy"
            @confirm="runBulk('delete')"
            @cancel="pendingBulkDelete = false"
        />

        <ConfirmDialog
            :open="pendingBulkSuspend"
            title="Suspend selected riddles?"
            :message="`Suspend ${selectedCount} selected riddle(s)? They will be hidden from players.`"
            confirm-label="Suspend"
            :busy="bulkBusy"
            @confirm="runBulk('suspend')"
            @cancel="pendingBulkSuspend = false"
        />

        <ConfirmDialog
            :open="pendingBulkUnsuspend"
            title="Unsuspend selected riddles?"
            :message="`Unsuspend ${selectedCount} selected riddle(s)?`"
            confirm-label="Unsuspend"
            :busy="bulkBusy"
            @confirm="runBulk('unsuspend')"
            @cancel="pendingBulkUnsuspend = false"
        />

        <ConfirmDialog
            :open="pendingCategoryChange"
            title="Move riddles to category?"
            :message="`Move ${selectedCount} selected riddle(s) to the chosen category?`"
            confirm-label="Move"
            :busy="bulkBusy"
            @confirm="runBulk('change_category')"
            @cancel="pendingCategoryChange = false"
        />
    </div>
</template>
