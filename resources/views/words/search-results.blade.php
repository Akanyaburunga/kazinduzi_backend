@extends('layouts.app')

@section('title', 'Search Results')

@section('content')
<div class="container">
    <h1>Search Results</h1>

    @if ($results->isEmpty())
    <div class="mt-6 p-4 bg-yellow-100 text-yellow-800 rounded-lg">
            <p>No results found for <strong>"{{ $query }}"</strong>.</p>
            <p>Would you like to contribute a new definition?</p>
            <a href="{{ route('words.create', ['word' => $query]) }}" class="mt-2 inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                Add "{{ $query }}"
            </a>
    </div>
    @else
        <ul class="list-group">
            @foreach ($results as $word)
                <li class="list-group-item">
                    <a href="{{ route('words.show', $word) }}">
                        <strong>{{ $word->word }}</strong>
                    </a>: {{ Str::limit($word->meanings->first()->meaning, 100) }}
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
