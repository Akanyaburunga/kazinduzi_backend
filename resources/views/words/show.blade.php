@extends('layouts.app')

@section('title', $word->word) <!-- Dynamic Page Title -->

@section('meta-description', Str::limit($word->meaning, 150)) <!-- Dynamic Meta Description -->

@section('content')
<div class="container">
<h1>{{ $word->word }}</h1>

<h3>Meanings:</h3>
<ul>
    @foreach ($word->meanings as $meaning)
        <li>
            <strong>{{ $meaning->user->name }}:</strong> {{ $meaning->meaning }}
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
