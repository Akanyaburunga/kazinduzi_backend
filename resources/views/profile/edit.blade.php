@extends('layouts.app')

@section('content')
<div class="container">
    <h1>{{ $user->name }}</h1>
    <p>Reputation: <strong>{{ $user->reputation }}</strong></p>

    <h3>Contributions:</h3>
    <ul class="list-group">
        @foreach ($user->meanings as $meaning)
            <li class="list-group-item">
                {{ $meaning->meaning }} ({{ $meaning->votes->sum(fn($vote) => $vote->vote === 'up' ? 1 : -1) }} votes)
            </li>
        @endforeach
    </ul>

    <h3>Activity Log:</h3>
    <ul class="list-group">
        @foreach ($user->reputationLogs as $log)
            <li class="list-group-item">
                <strong>{{ $log->change > 0 ? '+' : '' }}{{ $log->change }} points</strong>
                - {{ $log->reason }}
                <small class="text-muted">on {{ $log->created_at->format('d M Y, H:i') }}</small>
            </li>
        @endforeach
    </ul>
</div>
@endsection
