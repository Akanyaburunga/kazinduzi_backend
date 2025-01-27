@extends('layouts.app')

@section('content')
<div class="container my-5" style="max-width: 60%;">
    <div class="text-center mb-5">
        <h1 class="display-4">{{ $user->name }}</h1>
        <p class="lead">Reputation: <strong class="text-primary">{{ $user->reputation }}</strong></p>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
            <h3 class="mb-0">Activity Log</h3>
        </div>
        <div class="card-body">
            @if ($logs->isEmpty())
                <div class="alert alert-info text-center">
                    No reputation activity yet. Start contributing to earn reputation points!
                </div>
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($logs as $log)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong class="{{ $log->change > 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $log->change > 0 ? '+' : '' }}{{ $log->change }} points
                                </strong>
                                <span class="text-muted">- {{ $log->reason }}</span>
                            </div>
                            <small class="text-muted">
                                {{ $log->created_at->format('d M Y, H:i') }}
                            </small>
                        </li>
                    @endforeach
                </ul>

                <!-- Pagination Links -->
                <div class="mt-4">
                    {{ $logs->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

