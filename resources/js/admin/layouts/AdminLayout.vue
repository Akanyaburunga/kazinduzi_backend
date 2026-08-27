<script setup>
import { ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const route = useRoute();
const auth = useAuthStore();

const sidebarOpen = ref(false);

const nav = [
    { name: 'admin.dashboard', label: 'Dashboard', icon: 'M3 12l9-9 9 9M5 10v10h14V10' },
    { name: 'admin.riddles.index', label: 'Riddles', icon: 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z' },
    { name: 'admin.categories.index', label: 'Categories', icon: 'M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z' },
];

watch(() => route.name, () => {
    sidebarOpen.value = false;
});
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-40 w-64 bg-gray-900 text-gray-100 transition-transform lg:translate-x-0"
        >
            <div class="flex h-16 items-center border-b border-gray-700 px-6">
                <span class="text-lg font-semibold text-white">Kazinduzi Admin</span>
            </div>

            <nav class="mt-4 space-y-1 px-3">
                <router-link
                    v-for="item in nav"
                    :key="item.name"
                    :to="{ name: item.name }"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                    :class="route.name === item.name ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                    {{ item.label }}
                </router-link>
            </nav>
        </aside>

        <div v-if="sidebarOpen" class="fixed inset-0 z-30 bg-black/50 lg:hidden" @click="sidebarOpen = false"></div>

        <div class="lg:pl-64">
            <header class="flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 sm:px-6">
                <button
                    class="rounded-md p-2 text-gray-600 hover:bg-gray-100 lg:hidden"
                    @click="sidebarOpen = true"
                    aria-label="Open menu"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <div class="text-sm font-medium text-gray-700">
                    {{ route.meta?.title ?? 'Dashboard' }}
                </div>

                <div class="flex items-center gap-3 text-sm text-gray-700">
                    <span class="hidden sm:inline">{{ auth.user?.name }}</span>
                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">
                        {{ auth.user?.reputation }}
                    </span>
                </div>
            </header>

            <main class="p-4 sm:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
