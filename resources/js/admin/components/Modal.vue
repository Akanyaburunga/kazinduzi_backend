<script setup>
defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    size: { type: String, default: 'max-w-lg' },
});

const emit = defineEmits(['close']);
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4" @click.self="emit('close')">
                <div :class="size" class="mt-10 w-full rounded-lg bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3">
                        <h3 class="text-base font-semibold text-gray-900">{{ title }}</h3>
                        <button class="text-2xl leading-none text-gray-400 hover:text-gray-600" @click="emit('close')">&times;</button>
                    </div>
                    <div class="px-5 py-4">
                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: all 0.15s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
    transform: scale(0.97);
}
</style>
