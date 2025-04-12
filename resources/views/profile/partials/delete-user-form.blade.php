<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Futa ikonti yawe!') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Ufuse ikonti yawe, ibintu vyose vyakuranga bijana nayo ubudakosorwa. Imbere yo gufuta ikonti yawe, banza uvome ivyo vyose wipfuza kugumana ntibice bizimira.') }}
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Ndafuse ikonti yanje') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Koko wafashe ingingo yo gufuta ikonti yawe? 🥺') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Ufuse ikonti yawe, ibintu vyose vyakuranga bijana nayo ubudakosorwa. Shiramwo icandiko-kabanga cawe wemeze ko ikonti yawe ifutwa.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="{{ __('Password') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Ndisubiyeko') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('Ndafuse ikonti yanje') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
