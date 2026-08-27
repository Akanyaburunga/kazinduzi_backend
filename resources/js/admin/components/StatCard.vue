<script setup>
defineProps({
    label: { type: String, default: '' },
    value: { type: [String, Number], default: 0 },
    icon: { type: String, default: '' },
});
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">{{ label }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ value }}</p>
            </div>
            <div v-if="icon" class="rounded-md bg-indigo-50 p-3">
                <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="icon" />
                </svg>
            </div>
        </div>
    </div>
</template>
