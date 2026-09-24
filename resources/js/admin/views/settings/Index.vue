<script setup>
import { ref, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useSettingsStore } from '../../stores/settings.js';
import ConfirmDialog from '../../components/ConfirmDialog.vue';

const store = useSettingsStore();
const { guestLimits, loading, saving } = storeToRefs(store);

const resetOpen = ref(false);
const resetting = ref(false);

async function save() {
    const payload = {
        sokwe: Number(guestLimits.value.sokwe?.limit ?? 0),
        hera: Number(guestLimits.value.hera?.limit ?? 0),
        tuja: Number(guestLimits.value.tuja?.limit ?? 0),
    };
    await store.saveGuestLimits(payload);
}

async function reset() {
    resetting.value = true;
    try {
        await store.resetGuestLimits();
    } catch {
        // handled by store toast
    } finally {
        resetting.value = false;
        resetOpen.value = false;
    }
}

onMounted(() => store.fetchGuestLimits());
</script>

<template>
    <div class="space-y-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
            <p class="mt-1 text-sm text-gray-500">Guest play allowances and other runtime configuration.</p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Guest free plays</h2>
                <p class="mt-1 text-sm text-gray-500">
                    How many plays of each game a player may enjoy without an account — covers both
                    rounds started and classic puzzles answered in a mode. Once the allowance is
                    used up in a mode, the player is asked to create an account to keep playing.
                    Set to 0 to require an account before any play in that mode.
                </p>
            </div>

            <div v-if="loading" class="py-8 text-center text-sm text-gray-500">Loading…</div>

            <div v-else class="grid max-w-2xl gap-5 sm:grid-cols-3">
                <div v-for="mode in ['sokwe', 'hera', 'tuja']" :key="mode">
                    <label class="block text-sm font-medium text-gray-700">
                        {{ guestLimits[mode]?.name ?? mode }}
                    </label>
                    <input
                        v-model.number="guestLimits[mode].limit"
                        type="number"
                        min="0"
                        step="1"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p class="mt-1 text-xs text-gray-400">
                        Default: {{ guestLimits[mode]?.default ?? 0 }}
                        <span v-if="guestLimits[mode]?.configured"> · custom</span>
                    </p>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <button
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                    :disabled="saving || loading"
                    @click="save"
                >
                    {{ saving ? 'Saving…' : 'Save limits' }}
                </button>
                <button
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-60"
                    :disabled="saving || loading"
                    @click="resetOpen = true"
                >
                    Reset to defaults
                </button>
            </div>
        </div>

        <ConfirmDialog
            :open="resetOpen"
            title="Reset guest limits?"
            message="This restores the server defaults for free guest rounds in each game mode."
            confirm-label="Reset"
            :busy="resetting"
            @confirm="reset"
            @cancel="resetOpen = false"
        />
    </div>
</template>