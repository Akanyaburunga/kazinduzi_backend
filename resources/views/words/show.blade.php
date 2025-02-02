@extends('layouts.app')

@section('title', $word->word) <!-- Dynamic Page Title -->
@section('meta-description', Str::limit($word->meanings->pluck('meaning')->join(', '), 150)) <!-- Meta Description -->

@section('content')
<div class="max-w-3xl mx-auto p-6 bg-white shadow-md rounded-lg">
    <h1 class="text-3xl font-bold text-gray-800">{{ $word->word }}</h1>

    <!-- Meanings Section -->
    <h3 class="mt-4 text-xl font-semibold text-gray-700">Meanings:</h3>

    <ul class="mt-3 space-y-3">
        @foreach ($word->meanings as $meaning)
            <li class="p-4 bg-gray-100 rounded-lg shadow-sm flex justify-between items-start">
                <div>
                    <span class="text-sm text-gray-500">By <strong>{{ $meaning->user->name }}</strong></span>
                    <p class="text-gray-800">{{ $meaning->meaning }}</p>
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
                        <p class="text-gray-400 text-sm">Login to vote.</p>
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
                <textarea name="meaning" class="w-full p-3 border rounded-lg focus:ring focus:ring-blue-300" rows="3" placeholder="Add your meaning..."></textarea>
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition">
                    Submit Meaning
                </button>
            </form>
        </div>
    @endauth
</div>
@endsection
