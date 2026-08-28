<script setup>
import { ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { useToastStore } from '../stores/toast.js';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const toast = useToastStore();

const sidebarOpen = ref(false);

const nav = [
    { name: 'admin.dashboard', label: 'Dashboard', icon: 'M3 12l9-9 9 9M5 10v10h14V10' },
    { name: 'admin.riddles.index', label: 'Riddles', icon: 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z' },
    { name: 'admin.categories.index', label: 'Categories', icon: 'M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z' },
    { name: 'admin.tags.index', label: 'Tags', icon: 'M12 2l2.4 4.8L20 8l-4.8 3.6L16 18l-4-2.5L8 18l.8-6.4L4 8l5.6-1.2z' },
    { name: 'admin.achievements.index', label: 'Badges', icon: 'M8.21 13.89L5.7 12.5a2 2 0 011.4-3.74M22 12a5 5 0 00-7.07-4.53L9 9m0 0l2.47-7.07A5 5 0 0018 9l-9 0zM7.21 8.5L9 9l-2.47 7.07a5 5 0 00-3.27-2.32' },
];

watch(() => route.name, () => {
    sidebarOpen.value = false;
});

async function logout() {
    await auth.logout();
    toast.info('Signed out.');
    router.push({ name: 'admin.login' });
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-gray-900 text-gray-100 transition-transform lg:translate-x-0"
        >
            <div class="flex h-16 items-center gap-2.5 border-b border-gray-800 px-6">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600">
                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <span class="text-lg font-semibold text-white">Kazinduzi Admin</span>
            </div>

            <nav class="mt-4 flex-1 space-y-1 px-3">
                <router-link
                    v-for="item in nav"
                    :key="item.name"
                    :to="{ name: item.name }"
                    class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition-colors"
                    :class="route.name === item.name
                        ? 'bg-indigo-600 text-white shadow-sm'
                        : 'text-gray-300 hover:bg-gray-800 hover:text-white'"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                    {{ item.label }}
                </router-link>
            </nav>

            <div class="border-t border-gray-800 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-700 text-sm font-semibold text-white">
                        {{ (auth.user?.name ?? 'A').charAt(0).toUpperCase() }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-white">{{ auth.user?.name }}</p>
                        <p class="truncate text-xs text-gray-400">{{ auth.user?.email }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <div v-if="sidebarOpen" class="fixed inset-0 z-30 bg-black/50 lg:hidden" @click="sidebarOpen = false"></div>

        <div class="lg:pl-64">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-gray-200 bg-white/90 px-4 backdrop-blur sm:px-6">
                <div class="flex items-center gap-3">
                    <button
                        class="rounded-md p-2 text-gray-600 hover:bg-gray-100 lg:hidden"
                        @click="sidebarOpen = true"
                        aria-label="Open menu"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div class="text-base font-semibold text-gray-900">
                        {{ route.meta?.title ?? 'Dashboard' }}
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span class="hidden items-center gap-1.5 rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-700 sm:inline-flex">
                        <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                        {{ auth.user?.reputation }} rep
                    </span>
                    <button
                        class="flex items-center gap-1.5 rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50"
                        @click="logout"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Sign out
                    </button>
                </div>
            </header>

            <main class="p-4 sm:p-6">
                <router-view />
            </main>
        </div>
    </div>
</template>
