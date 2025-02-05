@extends('layouts.app')

@section('title', 'Home')

@section('content')
<div class="container">
    <!-- Hero Section with Search Bar -->
    <div class="row justify-content-center my-5">
        <div class="col-lg-8 text-center">
            <h1 class="display-4 text-primary">Welcome Kazinduzi</h1>
            <p class="lead text-muted">Define and explore words and their meanings, contributed by people like you!</p>
            <form action="{{ route('words.search') }}" method="GET">
                <div class="input-group">
                    <input type="text" class="form-control" name="query" placeholder="Search for words..." aria-label="Search for words">
                    <button class="btn btn-primary" type="submit">Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Featured Words Section -->
    <div class="row">
        <div class="col-12">
            <h2 class="h3 mb-3 text-muted">Featured Words</h2>
            <div class="card-deck">
                @foreach ($featuredWords as $word)
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">{{ $word->word }}</h5>
                            <p class="card-text">{{ Str::limit($word->meanings->first()->meaning, 100) }}</p>
                            <a href="{{ route('words.show', $word) }}" class="btn btn-outline-primary">View</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Trending Words Section -->
    <div class="row my-5">
        <div class="col-12">
            <h2 class="h3 mb-3 text-muted">Trending Words</h2>
            <ul class="list-group">
                @foreach ($trendingWords as $word)
                    <li class="list-group-item">
                        <strong>{{ $word->word }}</strong>
                        <span class="text-muted"> - {{ $word->created_at->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <!-- Recent Contributions Section -->
    <div class="row my-5">
        <div class="col-12">
            <h2 class="h3 mb-3 text-muted">Recent Contributions</h2>
            <ul class="list-group">
                @foreach ($recentContributions as $contribution)
                <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">{{ $contribution->word }}</h5>
                                <p class="card-text">{{ Str::limit($contribution->meanings->first()->meaning, 120) }}</p>
                                <a href="{{ route('words.show', $word) }}" class="btn btn-outline-primary">Learn More</a>
                            </div>
                            <div class="card-footer text-muted">
                                Added on {{ $contribution->created_at->format('F j, Y') }}
                            </div>
                </div>
                @endforeach
            </ul>
        </div>
    </div>

    <!-- Top Contributors Section -->
    <div class="row my-5">
        <div class="col-12">
            <h2 class="h3 mb-3 text-muted">Top Contributors</h2>
            <div class="list-group">
                @foreach ($topContributors as $contributor)
                <a href="{{ route('users.show', $contributor->id) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span>{{ $contributor->name }}</span>
                            <span class="badge bg-primary">{{ $contributor->reputation }}</span>
                </a>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Call to Action for Contributions -->
    <div class="text-center my-5">
        <a href="{{ route('words.create') }}" class="btn btn-success btn-lg">Start Contributing</a>
    </div>

</div>
@endsection
