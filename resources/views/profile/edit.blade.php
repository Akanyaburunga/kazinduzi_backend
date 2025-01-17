@extends('layouts.app')

@section('content')
<div class="container">
    <h1>{{ $user->name }}</h1>
    <p>Reputation: <strong>{{ $user->reputation }}</strong></p>

    <h3>Activity Log:</h3>
    @if ($logs->isEmpty())
        <div class="alert alert-info">
            No reputation activity yet. Start contributing to earn reputation points!
        </div>
    @else
        <ul class="list-group">
            @foreach ($logs as $log)
                <li class="list-group-item">
                    <strong>{{ $log->change > 0 ? '+' : '' }}{{ $log->change }} points</strong>
                    - {{ $log->reason }}
                    <small class="text-muted">on {{ $log->created_at->format('d M Y, H:i') }}</small>
                </li>
            @endforeach
        </ul>

        <!-- Pagination Links -->
        <div class="mt-3">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection
