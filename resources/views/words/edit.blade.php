@extends('layouts.app')

@section('title', 'Edit a Word')

@section('content')
<div class="container">
    <h1>Edit Word</h1>
    <form method="POST" action="{{ route('words.update', $word) }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="word" class="form-label">Word</label>
            <input type="text" class="form-control" id="word" name="word" value="{{ old('word', $word->word) }}" required>
        </div>

        <div class="mb-3">
            <label for="meaning" class="form-label">Meaning</label>
            <textarea class="form-control" id="meaning" name="meaning" rows="5" required>{{ $userMeaning->meaning }}</textarea>
        </div>

        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">Cancel</a>
    </form>
</div>
@endsection
