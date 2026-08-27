<script setup>
import Modal from './Modal.vue';

defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'Are you sure?' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Modal :open="open" :title="title" size="max-w-md" @close="emit('cancel')">
        <p class="text-sm text-gray-600">{{ message }}</p>
        <div class="mt-5 flex justify-end gap-3">
            <button
                class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                :disabled="busy"
                @click="emit('cancel')"
            >
                Cancel
            </button>
            <button
                class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                :disabled="busy"
                @click="emit('confirm')"
            >
                {{ confirmLabel }}
            </button>
        </div>
    </Modal>
</template>
