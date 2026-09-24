<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    joke: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
    serverErrors: { type: Object, default: () => ({}) },
    duplicate: { type: Object, default: null },
});

const emit = defineEmits(['close', 'submit']);

const form = ref({
    category_id: '',
    setup: '',
    punchline: '',
    distractors: ['', '', ''],
    source: '',
});

const errors = ref({});
const confirmClose = ref(false);

function pristine() {
    return {
        category_id: props.joke?.category_id ?? '',
        setup: props.joke?.setup ?? '',
        punchline: props.joke?.punchline ?? '',
        distractors: (props.joke?.distractors ?? ['', '', '']).slice(0, 3),
        source: props.joke?.source ?? '',
    };
}

function dirty() {
    return JSON.stringify(form.value) !== JSON.stringify(pristine());
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            errors.value = {};
            confirmClose.value = false;
            form.value = pristine();
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
    if (!form.value.setup.trim()) {
        errors.value.setup = 'Setup is required.';
    }
    if (!form.value.punchline.trim()) {
        errors.value.punchline = 'Punchline is required.';
    }
    if (Object.keys(errors.value).length) {
        return;
    }
    emit('submit', {
        ...form.value,
        distractors: form.value.distractors.map((d) => d.trim()).filter(Boolean),
    });
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/60 p-4 backdrop-blur-sm" @click.self="emit('close')">
            <div class="mt-10 w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ joke ? 'Edit Joke' : 'New Joke' }}
                    </h3>
                    <button class="rounded-md p-1 text-2xl leading-none text-gray-400 transition hover:bg-gray-100 hover:text-gray-600" @click="requestClose">&times;</button>
                </div>

                <form class="space-y-4 px-6 py-5" @submit.prevent="submit">
                    <div v-if="duplicate" class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.05 14a2 2 0 001.71 3h16.58a2 2 0 001.71-3l-8.05-14a2 2 0 00-3.42 0z" />
                        </svg>
                        <p class="text-sm text-amber-800">
                            A joke with this punchline already exists:
                            <span class="font-semibold">"{{ duplicate.setup }}"</span>.
                            Please use a different punchline.
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
                        <label class="block text-sm font-medium text-gray-700">Setup</label>
                        <textarea
                            v-model="form.setup"
                            rows="3"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                        <p v-if="fieldError('setup')" class="mt-1 text-xs text-red-600">{{ fieldError('setup') }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Punchline</label>
                        <input
                            v-model="form.punchline"
                            type="text"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="fieldError('punchline')" class="mt-1 text-xs text-red-600">{{ fieldError('punchline') }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Distractors</label>
                        <div class="mt-1 space-y-2">
                            <input
                                v-for="(d, i) in form.distractors"
                                :key="i"
                                v-model="form.distractors[i]"
                                type="text"
                                class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                :placeholder="'Wrong punchline ' + (i + 1)"
                            />
                        </div>
                        <p v-if="fieldError('distractors')" class="mt-1 text-xs text-red-600">{{ fieldError('distractors') }}</p>
                        <p class="mt-1 text-xs text-gray-400">The game serves the true punchline plus these as options (padded with other jokes if fewer than three).</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Source</label>
                        <input
                            v-model="form.source"
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
                            {{ saving ? 'Saving...' : joke ? 'Save changes' : 'Create joke' }}
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