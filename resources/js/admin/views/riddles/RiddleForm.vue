<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    riddle: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit']);

const form = ref({
    category_id: '',
    question: '',
    answer: '',
    hint: '',
});

const errors = ref({});

watch(
    () => props.open,
    (open) => {
        if (open) {
            errors.value = {};
            form.value = {
                category_id: props.riddle?.category_id ?? '',
                question: props.riddle?.question ?? '',
                answer: props.riddle?.answer ?? '',
                hint: props.riddle?.hint ?? '',
            };
        }
    }
);

function submit() {
    errors.value = {};
    if (!form.value.question.trim()) {
        errors.value.question = 'Question is required.';
    }
    if (!form.value.answer.trim()) {
        errors.value.answer = 'Answer is required.';
    }
    if (Object.keys(errors.value).length) {
        return;
    }
    emit('submit', { ...form.value });
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4" @click.self="emit('close')">
            <div class="mt-10 w-full max-w-lg rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3">
                    <h3 class="text-base font-semibold text-gray-900">
                        {{ riddle ? 'Edit Riddle' : 'New Riddle' }}
                    </h3>
                    <button class="text-2xl leading-none text-gray-400 hover:text-gray-600" @click="emit('close')">&times;</button>
                </div>

                <form class="space-y-4 px-5 py-4" @submit.prevent="submit">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Category</label>
                        <select
                            v-model="form.category_id"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">No category</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                {{ category.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Question</label>
                        <textarea
                            v-model="form.question"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                        <p v-if="errors.question" class="mt-1 text-xs text-red-600">{{ errors.question }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Answer</label>
                        <input
                            v-model="form.answer"
                            type="text"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="errors.answer" class="mt-1 text-xs text-red-600">{{ errors.answer }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Hint</label>
                        <input
                            v-model="form.hint"
                            type="text"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
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
                            {{ saving ? 'Saving...' : riddle ? 'Save' : 'Create' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
