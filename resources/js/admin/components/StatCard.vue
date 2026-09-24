<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, default: '' },
    value: { type: [String, Number], default: 0 },
    icon: { type: String, default: '' },
    tone: { type: String, default: 'indigo' },
});

const tones = {
    indigo: { tile: 'bg-indigo-50', icon: 'text-indigo-600' },
    emerald: { tile: 'bg-emerald-50', icon: 'text-emerald-600' },
    amber: { tile: 'bg-amber-50', icon: 'text-amber-600' },
    sky: { tile: 'bg-sky-50', icon: 'text-sky-600' },
    rose: { tile: 'bg-rose-50', icon: 'text-rose-600' },
    violet: { tile: 'bg-violet-50', icon: 'text-violet-600' },
};

const style = computed(() => tones[props.tone] || tones.indigo);
</script>

<template>
    <div class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">{{ label }}</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-gray-900">{{ value }}</p>
            </div>
            <div v-if="icon" class="rounded-lg p-3" :class="style.tile">
                <svg class="h-6 w-6" :class="style.icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="icon" />
                </svg>
            </div>
        </div>
    </div>
</template>
