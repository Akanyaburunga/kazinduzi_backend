@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Words List</h1>

    <!-- Search Form -->
    <form action="{{ route('words.index') }}" method="GET" class="mb-4">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Search for a word..." value="{{ request('search') }}">
            <button type="submit" class="btn btn-primary">Search</button>
        </div>
    </form>

    @if ($words->isEmpty())
        <p>No words found.</p>
    @else

        @foreach ($words as $word)
            <div class="card my-3">
                <div class="card-body">
                <h3>{!! str_replace($search, "<mark>$search</mark>", $word->word) !!}</h3>
                <p>{!! str_replace($search, "<mark>$search</mark>", $word->meaning) !!}</p>
                    <small>Submitted by: {{ $word->user->name }}</small>
                </div>
            </div>
        @endforeach

        <div class="d-flex justify-content-center">
            {{ $words->links() }}
        </div>
    @endif
</div>
@endsection
