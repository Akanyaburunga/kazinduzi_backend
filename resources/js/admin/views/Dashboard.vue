<script setup>
import StatCard from '../components/StatCard.vue';

const quickLinks = [
    { name: 'admin.riddles.index', label: 'Manage Riddles' },
    { name: 'admin.categories.index', label: 'Manage Categories' },
];
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">Manage the Kazinduzi platform.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <StatCard label="Riddles" value="—" icon="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
            <StatCard label="Categories" value="—" icon="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z" />
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Quick links</h2>
            <div class="mt-3 flex flex-wrap gap-3">
                <router-link
                    v-for="link in quickLinks"
                    :key="link.name"
                    :to="{ name: link.name }"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    {{ link.label }}
                </router-link>
            </div>
        </div>
    </div>
</template>
