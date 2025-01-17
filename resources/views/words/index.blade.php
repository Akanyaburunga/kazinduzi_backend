@extends('layouts.app')

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
        <div class="alert alert-warning">No words found.</div>
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
