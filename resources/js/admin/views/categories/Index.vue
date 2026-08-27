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
            <h1 class="text-xl font-semibold text-gray-900">Categories</h1>
            <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700" @click="openCreate">
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
