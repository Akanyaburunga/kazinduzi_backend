@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="container">
    <h1>Welcome, {{ $user->name }}</h1>
    <p>Here are your contributions:</p>

    @if($contributions->isEmpty())
        <p>You haven't added any words yet. Start contributing <a href="{{ route('words.create') }}">here</a>.</p>
    @else
        <ul class="list-group">
            @foreach($contributions as $word)
                <li class="list-group-item">
                    <a href="{{ route('words.show', $word) }}">
                        <strong>{{ $word->word }}</strong>
                    </a>: {{ $word->meaning }}
                    <br>
                    <small>Added on {{ $word->created_at->format('F j, Y') }}</small>
                </li>
            @endforeach
        </ul>

        <div class="mt-3">
            {{ $contributions->links() }} <!-- Pagination links -->
        </div>
    @endif
</div>
@endsection
