@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Words List</h1>
    @foreach ($words as $word)
        <div class="card my-3">
            <div class="card-body">
                <h3>{{ $word->word }}</h3>
                <p>{{ $word->meaning }}</p>
                <small>Submitted by: {{ $word->user->name }}</small>
            </div>
        </div>
    @endforeach

    <div class="d-flex justify-content-center">
    {{ $words->links() }}
    </div>

</div>
@endsection
