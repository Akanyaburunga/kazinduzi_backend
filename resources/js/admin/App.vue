<script setup>
import { computed } from 'vue';
import { useAuthStore } from './stores/auth.js';
import Toasts from './components/Toasts.vue';

const auth = useAuthStore();

const loading = computed(() => !auth.initialised && auth.loading);
</script>

<template>
    <div>
        <Toasts />

        <div v-if="loading" class="flex min-h-screen items-center justify-center bg-gray-100">
            <span class="text-sm text-gray-500">Loading...</span>
        </div>

        <router-view v-else />
    </div>
</template>
