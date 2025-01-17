@extends('layouts.app')

@section('title', $word->word) <!-- Dynamic Page Title -->

@section('meta-description', Str::limit($word->meaning, 150)) <!-- Dynamic Meta Description -->

@section('content')
<div class="container">
<h1>{{ $word->word }}</h1>

<h3>Meanings:</h3>

<ul class="list-group">
        @foreach ($word->meanings as $meaning)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong>{{ $meaning->user->name }}:</strong> {{ $meaning->meaning }}
                </div>
                <div>
                    <form action="{{ route('meanings.vote', $meaning) }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="vote" value="up">
                        <button type="submit" class="btn btn-sm btn-success">Upvote</button>
                    </form>
                    <form action="{{ route('meanings.vote', $meaning) }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="vote" value="down">
                        <button type="submit" class="btn btn-sm btn-danger">Downvote</button>
                    </form>
                    <span class="badge bg-primary">{{ $meaning->votes->sum(fn($vote) => $vote->vote === 'up' ? 1 : -1) }}</span>
                </div>
            </li>
        @endforeach
    </ul>

@auth
    <form action="{{ route('meanings.store', $word) }}" method="POST">
        @csrf
        <textarea name="meaning" class="form-control" rows="3" placeholder="Add your meaning..."></textarea>
        <button type="submit" class="btn btn-primary mt-2">Submit</button>
    </form>
@endauth
</div>
@endsection
