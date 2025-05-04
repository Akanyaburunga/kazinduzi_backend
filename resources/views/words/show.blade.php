@extends('layouts.app')

@section('title', $word->word) <!-- Dynamic Page Title -->

@section('meta-description', Str::limit($word->meanings->pluck('meaning')->join(', '), 150)) <!-- Meta Description -->

@section('content')
<div class="max-w-3xl mx-auto p-6 bg-white shadow-md rounded-lg">
    <h1 class="text-3xl font-bold text-gray-800">{{ $word->word }}</h1>

    <!-- Meanings Section -->
    <h3 class="mt-4 text-xl font-semibold text-gray-700">Insiguro:</h3>

    <ul class="mt-3 space-y-3">
        @foreach ($word->meanings as $meaning)
            <li class="p-4 bg-gray-100 rounded-lg shadow-sm flex justify-between items-start">
                <div>
                @php
                    $canModerate = auth()->check() && auth()->user()->reputation >= env('MODERATION_REPUTATION_THRESHOLD', 500);
                @endphp

                @if ($canModerate && !auth()->user()->is_banned)
                        <form action="{{ route('moderation.ban', $word->user->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to ban this user?');">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-sm">🚫 Ban User</button>
                        </form>
                @endif
                    
                @if ($canModerate)
                    <span class="text-sm text-gray-500">{{ $meaning->created_at->diffForHumans() }}</span>
                    <span class="text-sm text-gray-500">By <strong>{{ $meaning->user->name }}</strong></span>
                    <p class="text-gray-800">{!! $meaning->html !!}</p>

                    @if (!$meaning->is_suspended)
                        <form action="{{ route('moderation.suspend.meaning', $meaning->id) }}" method="POST" onsubmit="return confirm('Suspend this meaning?');">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-sm">⚠️ Suspend Meaning</button>
                        </form>
                    @else
                        <form action="{{ route('moderation.unsuspend.meaning', $meaning->id) }}" method="POST" onsubmit="return confirm('Unsuspend this meaning?');">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">✅ Unsuspend Meaning</button>
                        </form>
                    @endif

                @else
                    @if (!$meaning->is_suspended)
                        <span class="text-sm text-gray-500">{{ $meaning->created_at->diffForHumans() }}</span>
                        <span class="text-sm text-gray-500">By <strong>{{ $meaning->user->name }}</strong></span>
                        <p class="text-gray-800">{!! $meaning->html !!}</p>
                    @else
                        <span class="badge bg-danger">This meaning is suspended.</span>
                    @endif
                @endif

                </div>
                <div class="flex items-center space-x-2">
                    @auth
                        <form action="{{ route('meanings.vote', $meaning) }}" method="POST">
                            @csrf
                            <input type="hidden" name="vote" value="up">
                            <button type="submit" class="px-3 py-1 bg-green-500 text-white text-sm rounded-lg hover:bg-green-600 transition">
                                👍
                            </button>
                        </form>
                        <form action="{{ route('meanings.vote', $meaning) }}" method="POST">
                            @csrf
                            <input type="hidden" name="vote" value="down">
                            <button type="submit" class="px-3 py-1 bg-red-500 text-white text-sm rounded-lg hover:bg-red-600 transition">
                                👎
                            </button>
                        </form>
                    @else
                        <p class="text-gray-400 text-sm">Injira kugira utore.</p>
                    @endauth
                    <span class="text-sm font-bold text-gray-700 px-2 py-1 bg-blue-100 rounded-md">
                        {{ $meaning->votes->sum(fn($vote) => $vote->vote === 'up' ? 1 : -1) }}
                    </span>
                </div>
            </li>
        @endforeach
    </ul>

    <!-- Add New Meaning Form -->
    @auth
        <div class="mt-6">
            <form action="{{ route('meanings.store', $word) }}" method="POST" class="space-y-3">
                @csrf
                <textarea name="meaning" id="meaning" class="w-full p-3 border rounded-lg focus:ring focus:ring-blue-300" rows="3" placeholder="Add your meaning..."></textarea>
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition">
                    Rungika insiguro
                </button>
            </form>

            <script>
                const easyMDE = new EasyMDE({
                    element: document.getElementById("meaning"),
                    spellChecker: false,
                    autosave: {
                        enabled: false,
                        delay: 1000,
                        uniqueId: "meaning"
                    },
                    placeholder: "Write your article in Markdown..."
                });
            </script>
        </div>
    @endauth
</div>
@endsection
