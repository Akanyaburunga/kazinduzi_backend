@extends('layouts.app')

@section('title', $user->name . "'s Profile")

@section('content')
<div class="container">
    <h1>{{ $user->name }}</h1>
    <p>Reputation: <strong>{{ $user->reputation }}</strong></p>

    <h3>Recent Contributions:</h3>
    <ul class="list-group">
        @foreach ($user->words as $word)
            <li class="list-group-item">
                <a href="{{ route('words.show', $word) }}"><strong>{{ $word->word }}</strong></a>
            </li>
        @endforeach
    </ul>
</div>
@endsection
