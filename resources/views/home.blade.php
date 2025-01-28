@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Welcome to Kazinduzi!</h1>

    <!-- Recent Contributions -->
    <section class="mb-12">
        <h2 class="text-2xl font-semibold mb-4">Recent Contributions</h2>
        @if ($recentWords->isEmpty())
            <p class="text-gray-600">No recent contributions found.</p>
        @else
            <ul class="space-y-4">
                @foreach ($recentWords as $word)
                <li class="p-4 bg-white rounded-lg shadow flex flex-col">
                    <a href="{{ route('words.show', $word) }}" class="text-xl font-medium text-blue-600 hover:underline">
                        {{ $word->word }}
                    </a>
                    <p class="text-gray-700 mt-2">{{ $word->meanings->first()->meaning ?? 'No meaning available' }}</p>
                    <small class="text-gray-500 mt-1">By {{ optional($word->user)->name }} on {{ $word->created_at->format('F j, Y') }}</small>
                </li>
                @endforeach
            </ul>
            <!-- Pagination Links -->
            <div class="mt-6">
                {{ $recentWords->links('pagination::tailwind') }}
            </div>
        @endif
    </section>

    <!-- Top Contributors -->
    <section>
        <h2 class="text-2xl font-semibold mb-4">Top Contributors</h2>
        @if ($topContributors->isEmpty())
            <p class="text-gray-600">No top contributors found.</p>
        @else
            <ul class="space-y-4">
                @foreach ($topContributors as $user)
                <li class="p-4 bg-white rounded-lg shadow flex justify-between items-center">
                    <span class="text-gray-800 font-medium">{{ $user->name }}</span>
                    <span class="bg-blue-100 text-blue-800 text-sm font-semibold px-3 py-1 rounded-full">{{ $user->words_count }} contributions</span>
                </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
