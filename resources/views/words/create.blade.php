@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-4">Submit a New Word</h1>

    <form action="{{ route('words.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="word" class="form-label">Word</label>
            <input type="text" name="word" id="word" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="meaning" class="form-label">Meaning</label>
            <textarea name="meaning" id="meaning" class="form-control" rows="4" required></textarea>
        </div>
        <button type="submit" class="btn btn-success">Submit</button>
    </form>
</div>
@endsection
