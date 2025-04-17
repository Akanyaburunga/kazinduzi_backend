@extends('layouts.app')

@section('title', $user->name . "'s Umwidondoro")

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
                    <p class="text-muted">Ndi umunyamuryango kuva {{ $user->created_at->format('F Y') }}</p>

                    <!-- Reputation Badge -->
                    <span class="badge bg-success fs-5">Amanota afise: {{ $user->reputation }}</span>

                    <hr>

                    <!-- User Stats -->
                    <div class="row">
                        <div class="col">
                            <h5>{{ $user->words->count() }}</h5>
                            <p class="text-muted">Ayo maze guterera</p>
                        </div>
                        <div class="col">
                            <h5>{{ $user->meanings->count() }}</h5>
                            <p class="text-muted">Insiguro maze gutanga</p>
                        </div>
                        <div class="col">
                            <h5>{{ $user->received_votes_count ?? 0 }}</h5>
                            <p class="text-muted">Amajwi maze kuronka</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Contributions -->
            <div class="mt-4">
                <h4>Intererano mperuka gutanga</h4>

                @if($contributions->isEmpty())
                    <p class="alert alert-info">Nta ntererano ndatanga.</p>
                @else
                    <ul class="list-group">
                        @foreach ($contributions as $word)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('words.show', $word) }}" class="fw-bold text-decoration-none">{{ $word->word }}</a>
                                <small class="text-muted">{{ $word->created_at->format('M d, Y') }}</small>
                            </li>
                        @endforeach
                    </ul>
                    <p>
                        {{ $contributions->links() }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
