<script setup>
import { ref, onMounted } from 'vue';
import axios from '../bootstrap.js';
import StatCard from '../components/StatCard.vue';

const stats = ref({
    total_riddles: 0,
    suspended_riddles: 0,
    total_categories: 0,
    total_attempts: 0,
    correct_attempts: 0,
});

const loading = ref(true);

const quickLinks = [
    { name: 'admin.riddles.index', label: 'Manage Riddles' },
    { name: 'admin.categories.index', label: 'Manage Categories' },
];

onMounted(async () => {
    try {
        const { data } = await axios.get('/admin/api/dashboard');
        stats.value = data.data;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">Manage the Kazinduzi platform.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <StatCard label="Riddles" :value="loading ? '—' : stats.total_riddles" icon="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
            <StatCard label="Categories" :value="loading ? '—' : stats.total_categories" icon="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z" />
            <StatCard label="Attempts" :value="loading ? '—' : stats.total_attempts" icon="M12 6v6h4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            <StatCard label="Correct attempts" :value="loading ? '—' : stats.correct_attempts" icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            <StatCard label="Suspended riddles" :value="loading ? '—' : stats.suspended_riddles" icon="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
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
