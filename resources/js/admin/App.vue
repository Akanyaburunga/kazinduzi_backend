<script setup>
import { computed } from 'vue';
import { useAuthStore } from './stores/auth.js';
import AdminLayout from './layouts/AdminLayout.vue';
import Toasts from './components/Toasts.vue';

const auth = useAuthStore();

const authenticated = computed(() => auth.isAuthenticated && auth.isAdmin && auth.initialised);
const loading = computed(() => !auth.initialised && auth.loading);
</script>

<template>
    <div>
        <Toasts />

        <div v-if="loading" class="flex min-h-screen items-center justify-center bg-gray-100">
            <span class="text-sm text-gray-500">Loading…</span>
        </div>

        <AdminLayout v-else-if="authenticated">
            <router-view />
        </AdminLayout>

        <router-view v-else />
    </div>
</template>
