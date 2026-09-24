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
            <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/60 p-4 backdrop-blur-sm" @click.self="emit('close')">
                <div :class="size" class="mt-10 w-full overflow-hidden rounded-xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
                        <button class="rounded-md p-1 text-2xl leading-none text-gray-400 transition hover:bg-gray-100 hover:text-gray-600" @click="emit('close')">&times;</button>
                    </div>
                    <div class="px-6 py-5">
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
