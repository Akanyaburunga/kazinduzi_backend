<script setup>
import { ref, computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useCategoriesStore } from '../../stores/categories.js';
import DataTable from '../../components/DataTable.vue';
import ConfirmDialog from '../../components/ConfirmDialog.vue';
import CategoryForm from './CategoryForm.vue';

const store = useCategoriesStore();
const { items, loading } = storeToRefs(store);

const showForm = ref(false);
const editing = ref(null);
const pendingDelete = ref(null);
const actionBusy = ref(false);

const columns = [
    { key: 'name', label: 'Name', sortable: true },
    { key: 'slug', label: 'Slug' },
    { key: 'riddles_count', label: 'Riddles' },
    { key: 'created_at', label: 'Created' },
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

const deleteMessage = computed(() =>
    `Delete the category "${pendingDelete.value?.name ?? ''}"? Associated riddles will keep existing with no category.`
);

onMounted(() => store.fetch());
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Categories</h1>
                <p class="mt-1 text-sm text-gray-500">Organise riddles by topic.</p>
            </div>
            <button
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="openCreate"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                New Category
            </button>
        </div>

        <DataTable
            :columns="columns"
            :items="items"
            :loading="loading"
            id-field="id"
            @sort="store.sort"
        >
            <template #cell-created_at="{ value }">
                {{ new Date(value).toLocaleDateString() }}
            </template>
            <template #actions="{ row }">
                <div class="flex justify-end gap-2">
                    <button class="text-sm text-indigo-600 hover:underline" @click="openEdit(row)">Edit</button>
                    <button class="text-sm text-red-600 hover:underline" @click="pendingDelete = row">Delete</button>
                </div>
            </template>
        </DataTable>

        <CategoryForm
            :open="showForm"
            :category="editing"
            :saving="store.saving"
            @close="showForm = false"
            @submit="submitForm"
        />

        <ConfirmDialog
            :open="pendingDelete !== null"
            title="Delete category?"
            :message="deleteMessage"
            confirm-label="Delete"
            :busy="actionBusy"
            @confirm="confirmDelete"
            @cancel="pendingDelete = null"
        />
    </div>
</template>
