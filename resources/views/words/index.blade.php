@extends('layouts.app')

@section('title', 'Words list')

@section('content')
<div class="container">
    <h1 class="mb-4">Words List</h1>

    <form action="{{ route('words.index') }}" method="GET" class="mb-4">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Search for a word..." value="{{ request('search') }}">
            <button type="submit" class="btn btn-primary">Search</button>
        </div>
    </form>

    @if ($words->isEmpty())
    <div class="mt-6 p-4 bg-yellow-100 text-yellow-800 rounded-lg">
            <p>No results found for <strong>"{{ $search }}"</strong>.</p>
            <p>Would you like to contribute a new definition?</p>
            <a href="{{ route('words.create', ['word' => $search]) }}" class="mt-2 inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                Add "{{ $search }}"
            </a>
    </div>
    @else
        @foreach ($words as $word)
            <div class="card my-3">
                <div class="card-body">
                <a href="{{ route('words.show', $word) }}">{{ $word->word }}</a>
                <span class="badge bg-secondary">{{ $word->meanings->count() }} meanings</span>

                </div>
            </div>
        @endforeach

        <div class="d-flex justify-content-center">
            {{ $words->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
