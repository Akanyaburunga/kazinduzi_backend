<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    riddle: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
    serverErrors: { type: Object, default: () => ({}) },
    duplicate: { type: Object, default: null },
});

const emit = defineEmits(['close', 'submit']);

const form = ref({
    category_id: '',
    question: '',
    answer: '',
    hint: '',
});

const errors = ref({});
const confirmClose = ref(false);

function dirty() {
    const pristine = {
        category_id: props.riddle?.category_id ?? '',
        question: props.riddle?.question ?? '',
        answer: props.riddle?.answer ?? '',
        hint: props.riddle?.hint ?? '',
    };
    return JSON.stringify(form.value) !== JSON.stringify(pristine);
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            errors.value = {};
            confirmClose.value = false;
            form.value = {
                category_id: props.riddle?.category_id ?? '',
                question: props.riddle?.question ?? '',
                answer: props.riddle?.answer ?? '',
                hint: props.riddle?.hint ?? '',
            };
        }
    }
);

function fieldError(field) {
    return errors.value[field] || props.serverErrors[field]?.[0];
}

function requestClose() {
    if (dirty() && !props.saving) {
        confirmClose.value = true;
        return;
    }
    emit('close');
}

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
        <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/60 p-4 backdrop-blur-sm" @click.self="emit('close')">
            <div class="mt-10 w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ riddle ? 'Edit Riddle' : 'New Riddle' }}
                    </h3>
                    <button class="rounded-md p-1 text-2xl leading-none text-gray-400 transition hover:bg-gray-100 hover:text-gray-600" @click="requestClose">&times;</button>
                </div>

                <form class="space-y-4 px-6 py-5" @submit.prevent="submit">
                    <div v-if="duplicate" class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.05 14a2 2 0 001.71 3h16.58a2 2 0 001.71-3l-8.05-14a2 2 0 00-3.42 0z" />
                        </svg>
                        <p class="text-sm text-amber-800">
                            A riddle with this answer already exists:
                            <span class="font-semibold">"{{ duplicate.question }}"</span>.
                            Please use a different answer or category.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Category</label>
                        <select
                            v-model="form.category_id"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                        <p v-if="fieldError('question')" class="mt-1 text-xs text-red-600">{{ fieldError('question') }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Answer</label>
                        <input
                            v-model="form.answer"
                            type="text"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="fieldError('answer')" class="mt-1 text-xs text-red-600">{{ fieldError('answer') }}</p>
                        <p class="mt-1 text-xs text-gray-400">Stored lower-cased without accents or extra spaces.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Hint</label>
                        <input
                            v-model="form.hint"
                            type="text"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                            :disabled="saving"
                            @click="requestClose"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60"
                            :disabled="saving"
                        >
                            {{ saving ? 'Saving...' : riddle ? 'Save changes' : 'Create riddle' }}
                        </button>
                    </div>
                </form>
            </div>

            <div v-if="confirmClose" class="mt-10 w-full max-w-sm rounded-xl bg-white p-6 shadow-2xl">
                <h3 class="text-base font-semibold text-gray-900">Discard changes?</h3>
                <p class="mt-1 text-sm text-gray-500">You have unsaved changes. Leaving now will discard them.</p>
                <div class="mt-4 flex justify-end gap-3">
                    <button class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50" @click="confirmClose = false">
                        Keep editing
                    </button>
                    <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700" @click="confirmClose = false; emit('close')">
                        Discard
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
