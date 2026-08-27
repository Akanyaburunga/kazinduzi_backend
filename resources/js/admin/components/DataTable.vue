<script setup>
defineProps({
    columns: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    idField: { type: String, default: 'id' },
    loading: { type: Boolean, default: false },
    searchable: { type: Boolean, default: false },
    search: { type: String, default: '' },
    sortField: { type: String, default: null },
    sortDir: { type: String, default: 'asc' },
    meta: { type: Object, default: null },
});

defineEmits(['update:search', 'sort', 'page']);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="w-full sm:max-w-xs">
                <div v-if="searchable" class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                    </svg>
                    <input
                        :value="search"
                        type="search"
                        class="block w-full rounded-lg border-gray-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Search..."
                        @input="$emit('update:search', $event.target.value)"
                    />
                </div>
            </div>
            <slot name="toolbar" />
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                        >
                            <template v-if="column.sortable">
                                <button class="inline-flex items-center gap-1 hover:text-gray-700" @click="$emit('sort', column.key)">
                                    {{ column.label }}
                                    <span v-if="sortField === column.key" class="text-gray-900">
                                        {{ sortDir === 'asc' ? '↑' : '↓' }}
                                    </span>
                                </button>
                            </template>
                            <template v-else>
                                {{ column.label }}
                            </template>
                        </th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-if="loading">
                        <td :colspan="columns.length + 1" class="px-4 py-8 text-center text-gray-400">Loading...</td>
                    </tr>
                    <tr v-else-if="items.length === 0">
                        <td :colspan="columns.length + 1" class="px-4 py-8 text-center text-gray-400">No results found.</td>
                    </tr>
                    <tr v-for="row in items" v-else :key="row[idField]" class="hover:bg-gray-50">
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="whitespace-nowrap px-4 py-3 text-gray-900"
                        >
                            <slot :name="'cell-' + column.key" :row="row" :value="row[column.key]">
                                {{ row[column.key] }}
                            </slot>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <slot name="actions" :row="row" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between text-sm text-gray-600">
            <span>
                Page {{ meta.current_page }} of {{ meta.last_page }}
            </span>
            <div class="flex gap-2">
                <button
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-600 transition hover:bg-gray-50 disabled:opacity-40"
                    :disabled="meta.current_page <= 1"
                    @click="$emit('page', meta.current_page - 1)"
                >
                    Previous
                </button>
                <button
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-600 transition hover:bg-gray-50 disabled:opacity-40"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="$emit('page', meta.current_page + 1)"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
</template>
