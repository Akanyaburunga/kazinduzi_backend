<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { useToastStore } from '../stores/toast.js';

const router = useRouter();
const auth = useAuthStore();
const toast = useToastStore();

const form = ref({ email: '', password: '', remember: false });
const errors = ref({});
const submitting = ref(false);

async function submit() {
    errors.value = {};
    if (!form.value.email.trim()) {
        errors.value.email = 'Email is required.';
    }
    if (!form.value.password) {
        errors.value.password = 'Password is required.';
    }
    if (Object.keys(errors.value).length) {
        return;
    }

    submitting.value = true;
    const result = await auth.login({
        email: form.value.email,
        password: form.value.password,
        remember: form.value.remember,
    });
    submitting.value = false;

    if (result.ok) {
        toast.success('Welcome back.');
        router.push({ name: 'admin.dashboard' });
        return;
    }

    if (result.errors?.email) {
        errors.value.email = result.errors.email[0];
    }
    errors.value.form = result.message || 'These credentials do not match our records.';
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-gray-100 via-gray-50 to-indigo-100 px-4 py-10">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-600 shadow-lg shadow-indigo-600/30">
                    <svg class="h-7 w-7 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">Kazinduzi Admin</h1>
                <p class="mt-1 text-sm text-gray-500">Sign in to manage the platform</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-xl shadow-gray-200/50">
                <form class="space-y-5" @submit.prevent="submit">
                    <p
                        v-if="errors.form"
                        class="flex items-start gap-2 rounded-lg bg-red-50 px-3 py-2.5 text-sm text-red-600"
                    >
                        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        {{ errors.form }}
                    </p>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email address</label>
                        <input
                            v-model="form.email"
                            type="email"
                            autocomplete="username"
                            class="mt-1.5 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="you@example.com"
                        />
                        <p v-if="errors.email" class="mt-1 text-xs text-red-600">{{ errors.email }}</p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <label class="block text-sm font-medium text-gray-700">Password</label>
                        </div>
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            class="mt-1.5 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="••••••••"
                        />
                        <p v-if="errors.password" class="mt-1 text-xs text-red-600">{{ errors.password }}</p>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            Remember me
                        </label>
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60"
                        :disabled="submitting"
                    >
                        {{ submitting ? 'Signing in...' : 'Sign in' }}
                    </button>
                </form>
            </div>

            <p class="mt-6 text-center text-xs text-gray-400">Authorised moderators only.</p>
        </div>
    </div>
</template>
