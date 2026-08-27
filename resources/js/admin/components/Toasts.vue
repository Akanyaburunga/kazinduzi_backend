<script setup>
import { useToastStore } from '../stores/toast.js';

const toast = useToastStore();

const styles = {
    success: 'bg-green-600 text-white',
    error: 'bg-red-600 text-white',
    info: 'bg-blue-600 text-white',
};
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-center gap-2 px-4">
        <TransitionGroup name="toast">
            <div
                v-for="t in toast.toasts"
                :key="t.id"
                class="pointer-events-auto flex w-full max-w-sm items-center justify-between rounded-md px-4 py-3 text-sm font-medium shadow-lg"
                :class="styles[t.type] || styles.info"
            >
                <span>{{ t.message }}</span>
                <button class="ml-4 text-lg leading-none opacity-70 hover:opacity-100" @click="toast.remove(t.id)">&times;</button>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.25s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
