<script setup>
import { ref, computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useTagsStore } from '../../stores/tags.js';
import DataTable from '../../components/DataTable.vue';
import ConfirmDialog from '../../components/ConfirmDialog.vue';

const store = useTagsStore();
const { items, loading } = storeToRefs(store);

const showForm = ref(false);
const editing = ref(null);
const pendingDelete = ref(null);
const actionBusy = ref(false);
const form = ref({ name: '', slug: '' });

const columns = [
    { key: 'name', label: 'Name', sortable: true },
    { key: 'slug', label: 'Slug' },
    { key: 'riddles_count', label: 'Riddles' },
    { key: 'created_at', label: 'Created' },
];

function openCreate() {
    editing.value = null;
    form.value = { name: '', slug: '' };
    showForm.value = true;
}

function openEdit(row) {
    editing.value = row;
    form.value = { name: row.name, slug: row.slug };
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

const deleteMessage = computed(() =>
    `Delete the tag "${pendingDelete.value?.name ?? ''}"? Riddles keep existing with the tag removed.`
);

onMounted(() => store.fetch());
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Tags</h1>
                <p class="mt-1 text-sm text-gray-500">Create themed tags used to curate riddle collections.</p>
            </div>
            <button
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="openCreate"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                New Tag
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

        <Teleport to="body">
            <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4 backdrop-blur-sm" @click.self="showForm = false">
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-900">{{ editing ? 'Edit Tag' : 'New Tag' }}</h3>
                    <form class="mt-4 space-y-4" @submit.prevent="submitForm">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Slug</label>
                            <input
                                v-model="form.slug"
                                type="text"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <p class="mt-1 text-xs text-gray-400">Leave blank to auto-generate from the name.</p>
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
                                {{ store.saving ? 'Saving...' : editing ? 'Save changes' : 'Create tag' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <ConfirmDialog
            :open="pendingDelete !== null"
            title="Delete tag?"
            :message="deleteMessage"
            confirm-label="Delete"
            :busy="actionBusy"
            @confirm="confirmDelete"
            @cancel="pendingDelete = null"
        />
    </div>
</template>
