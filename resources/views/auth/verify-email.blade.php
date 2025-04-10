<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ __('Urakoze kwiyandikisha! Imbere yo gutangura guterera ataco winona, wodufasha gusuzuma imeyile yawe ufyonze agahora twakurungikiye kuri imeyile ? Ukaba ata butumwa uraronka, fyonda hepfo tukurungikire ubundi butumwa.') }}
    </div>

    @if (session('status') == 'Agahora ko gusuzuma kamaze kurungikwa!')
        <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
            {{ __('Agahora gashasha ko gusuzuma kamaze kurungikwa kuri ya imeyile wakoresha wiyandikisha.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Subira murungike ubutumwa bwo gusuzuma') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                {{ __('Gusohoka') }}
            </button>
        </form>
    </div>
</x-guest-layout>
