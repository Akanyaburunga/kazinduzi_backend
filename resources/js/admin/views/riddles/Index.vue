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
const pendingDelete = ref(null);
const pendingSuspend = ref(null);
const pendingUnsuspend = ref(null);
const actionBusy = ref(false);

const columns = [
    { key: 'question', label: 'Question', sortable: true },
    { key: 'answer', label: 'Answer', sortable: true },
    { key: 'category', label: 'Category' },
    { key: 'status', label: 'Status' },
    { key: 'attempts_count', label: 'Attempts' },
];

function openCreate() {
    editing.value = null;
    showForm.value = true;
}

function openEdit(row) {
    editing.value = row;
    showForm.value = true;
}

async function submitForm(payload) {
    try {
        await store.save(payload, editing.value?.id);
        showForm.value = false;
    } catch {
        // toast already emitted by the store
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
            <h1 class="text-xl font-semibold text-gray-900">Riddles</h1>
            <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700" @click="openCreate">
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
            @close="showForm = false"
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
