@extends('layouts.app')

@section('title', $user->name . "'s Profile")

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <!-- Profile Card -->
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <!-- User Avatar -->
                    <img src="https://www.gravatar.com/avatar/{{ md5(strtolower(trim($user->email))) }}?s=120&d=mp" 
                         class="rounded-circle mb-3" alt="User Avatar">
                    
                    <h2 class="mb-1">{{ $user->name }}</h2>
                    <p class="text-muted">Member since {{ $user->created_at->format('F Y') }}</p>

                    <!-- Reputation Badge -->
                    <span class="badge bg-success fs-5">Reputation: {{ $user->reputation }}</span>

                    <hr>

                    <!-- User Stats -->
                    <div class="row">
                        <div class="col">
                            <h5>{{ $user->words->count() }}</h5>
                            <p class="text-muted">Contributions</p>
                        </div>
                        <div class="col">
                            <h5>{{ $user->meanings->count() }}</h5>
                            <p class="text-muted">Meanings Added</p>
                        </div>
                        <div class="col">
                            <h5>{{ $user->received_votes_count ?? 0 }}</h5>
                            <p class="text-muted">Votes Received</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Contributions -->
            <div class="mt-4">
                <h4>Recent Contributions</h4>

                @if($user->words->isEmpty())
                    <p class="alert alert-info">No contributions yet.</p>
                @else
                    <ul class="list-group">
                        @foreach ($user->words as $word)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('words.show', $word) }}" class="fw-bold text-decoration-none">{{ $word->word }}</a>
                                <small class="text-muted">{{ $word->created_at->format('M d, Y') }}</small>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
