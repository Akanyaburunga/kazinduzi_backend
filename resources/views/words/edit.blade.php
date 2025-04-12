@extends('layouts.app')

@section('title', 'Hubūra ijambo watanze')

@section('content')
<div class="container">

    <div class="row justify-content-center my-5">
            <div class="col-lg-8 text-center">
            <h3 class="display-6 text-primary">Hubūra</h3>
            </div>
    </div>

    <form method="POST" action="{{ route('words.update', $word) }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="word" class="form-label">Ijambo</label>
            <input type="text" class="form-control" id="word" name="word" value="{{ old('word', $word->word) }}" required>
        </div>

        <div class="mb-3">
            <label for="meaning" class="form-label">Insiguro</label>
            <textarea class="form-control" id="meaning" name="meaning" rows="5" required>{{ $userMeaning->meaning }}</textarea>
        </div>

        <button type="submit" class="btn btn-primary">Ndêmeje ivyahinduwe</button>
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">Ndisubiyeko</a>
    </form>
</div>
@endsection
