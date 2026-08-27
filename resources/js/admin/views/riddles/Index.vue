<script setup>
import { ref, computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useToastStore } from '../../stores/toast.js';
import { useRiddlesStore } from '../../stores/riddles.js';
import { useCategoriesStore } from '../../stores/categories.js';
import DataTable from '../../components/DataTable.vue';
import ConfirmDialog from '../../components/ConfirmDialog.vue';
import RiddleForm from './RiddleForm.vue';

const toast = useToastStore();
const store = useRiddlesStore();
const categoriesStore = useCategoriesStore();

const { items, meta, loading, search, sortField, sortDir } = storeToRefs(store);
const { items: categories } = storeToRefs(categoriesStore);

const showForm = ref(false);
const editing = ref(null);
const serverErrors = ref({});
const duplicate = ref(null);
const pendingDelete = ref(null);
const pendingSuspend = ref(null);
const pendingUnsuspend = ref(null);
const actionBusy = ref(false);

const columns = [
    { key: 'question', label: 'Question', sortable: true },
    { key: 'answer', label: 'Answer', sortable: true },
    { key: 'difficulty', label: 'Difficulty', sortable: true },
    { key: 'category', label: 'Category' },
    { key: 'source', label: 'Source' },
    { key: 'status', label: 'Status' },
    { key: 'attempts_count', label: 'Attempts', sortable: true },
    { key: 'solved_count', label: 'Solved', sortable: true },
    { key: 'success_rate', label: 'Success %' },
];

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

const deleteMessage = computed(() =>
    `Delete the riddle: "${pendingDelete.value?.question ?? ''}"? This cannot be undone.`
);

onMounted(async () => {
    await Promise.all([store.fetch(), categoriesStore.fetch()]);
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
            @update:search="store.setSearch"
            @sort="store.sort"
            @page="store.setPage"
        >
            <template #cell-category="{ row }">
                {{ row.category?.name ?? '—' }}
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
                    <button
                        v-if="row.is_suspended"
                        class="text-sm text-green-600 hover:underline"
                        @click="pendingUnsuspend = row"
                    >
                        Unsuspend
                    </button>
                    <button v-else class="text-sm text-amber-600 hover:underline" @click="pendingSuspend = row">Suspend</button>
                    <button class="text-sm text-red-600 hover:underline" @click="pendingDelete = row">Delete</button>
                </div>
            </template>
        </DataTable>

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
    </div>
</template>
