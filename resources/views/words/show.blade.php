@extends('layouts.app')

@section('title', $word->word) <!-- Dynamic Page Title -->

@section('meta-description', Str::limit($word->meaning, 150)) <!-- Dynamic Meta Description -->

@section('content')
<div class="container">
    <h1>{{ $word->word }}</h1>
    <p>{{ $word->meaning }}</p>

    <p><small>Added by {{ $word->user->name }} on {{ $word->created_at->format('F j, Y') }}</small></p>

    <a href="{{ route('home') }}" class="btn btn-primary">Back to Home</a>
</div>
@endsection
