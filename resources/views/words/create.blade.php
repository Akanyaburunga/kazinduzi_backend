@extends('layouts.app')

@section('title', 'Create a word')

@section('content')
<div class="container">
    <h1 class="mb-4">Terera ijambo rishasha!</h1>

    <form action="{{ route('words.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="word" class="form-label">Ijambo</label>
            <input type="text" name="word" value="{{ request('word') }}" id="word" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="meaning" class="form-label">Insiguro</label>
            <textarea name="meaning" id="meaning" class="form-control" rows="4" required></textarea>
        </div>
        <button type="submit" class="btn btn-success">Ndaterereye 🤩</button>
    </form>
</div>
@endsection
