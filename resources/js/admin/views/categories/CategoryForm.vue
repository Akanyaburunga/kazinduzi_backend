<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    category: { type: Object, default: null },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit']);

const form = ref({
    name: '',
    slug: '',
    description: '',
});
const errors = ref({});

watch(
    () => props.open,
    (open) => {
        if (open) {
            errors.value = {};
            form.value = {
                name: props.category?.name ?? '',
                slug: props.category?.slug ?? '',
                description: props.category?.description ?? '',
            };
        }
    }
);

function submit() {
    errors.value = {};
    if (!form.value.name.trim()) {
        errors.value.name = 'Name is required.';
    }
    if (Object.keys(errors.value).length) {
        return;
    }
    emit('submit', {
        name: form.value.name,
        slug: form.value.slug || undefined,
        description: form.value.description || undefined,
    });
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4" @click.self="emit('close')">
            <div class="mt-10 w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3">
                    <h3 class="text-base font-semibold text-gray-900">
                        {{ category ? 'Edit Category' : 'New Category' }}
                    </h3>
                    <button class="text-2xl leading-none text-gray-400 hover:text-gray-600" @click="emit('close')">&times;</button>
                </div>

                <form class="space-y-4 px-5 py-4" @submit.prevent="submit">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Slug</label>
                        <input
                            v-model="form.slug"
                            type="text"
                            placeholder="Leave empty to auto-generate"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            :disabled="saving"
                            @click="emit('close')"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                            :disabled="saving"
                        >
                            {{ saving ? 'Saving...' : category ? 'Save' : 'Create' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
