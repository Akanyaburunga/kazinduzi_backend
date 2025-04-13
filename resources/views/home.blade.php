@extends('layouts.app')

@section('title', 'Ishikiro')

@section('content')
<div class="container">
    <!-- Hero Section with Search Bar -->
    <div class="row justify-content-center my-5">
        <div class="col-lg-8 text-center">
        <h1 class="display-4 text-primary">Kaze kuri Kazīndūzi</h1>
        <p class="lead text-muted">Imare amazinda nawe uyamare abandi mu guterera ico uzi kuri Kazīndūzi!</p>
            <form action="{{ route('words.search') }}" method="GET">
                <div class="input-group">
                    <input type="text" class="form-control" id="search-input" name="query" placeholder="Rondera ijambo wipfuza" aria-label="Rondera ijambo wipfuza" autocomplete="off">
                    <ul id="search-results" class="list-group mt-2" style="display: none;"></ul>
                    <button class="btn btn-primary" type="submit">Rondera</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Featured Words Section -->
    <div class="row">
        <div class="col-12">
            <h2 class="h3 mb-3 text-muted">Amajambo twabatoreye</h2>
            <div class="card-deck">
                @foreach ($featuredWords as $word)
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">{{ $word->word }}</h5>
                            <p class="card-text">{{ Str::limit($word->meanings->first()->meaning, 100) }}</p>
                            <a href="{{ route('words.show', $word) }}" class="btn btn-outline-primary">Raba</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Trending Words Section -->
    <div class="row my-5">
        <div class="col-12">
            <h2 class="h3 mb-3 text-muted">Amajambo akunzwe muri aka kanya</h2>
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
            <h2 class="h3 mb-3 text-muted">Amajambo muheruka guterera</h2>
            <ul class="list-group">
                @foreach ($recentContributions as $contribution)
                <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">{{ $contribution->word }}</h5>
                                <p class="card-text">{{ Str::limit($contribution->meanings->first()->meaning, 120) }}</p>
                                <a href="{{ route('words.show', $word) }}" class="btn btn-outline-primary">Soma vyose</a>
                            </div>
                            <div class="card-footer text-muted">
                                Ryagiyeko ku wa {{ $contribution->created_at->format('F j, Y') }}
                            </div>
                </div>
                @endforeach
            </ul>
        </div>
    </div>

    <!-- Top Contributors Section -->
    <div class="row my-5">
        <div class="col-12">
            <h2 class="h3 mb-3 text-muted">Abaterereye menshi</h2>
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
        <a href="{{ route('words.create') }}" class="btn btn-success btn-lg">Tanga intererano yawe</a>
    </div>

</div>

<!-- Put this just before the closing </body> tag -->
<script>
    $(document).ready(function() {
        $('#search-input').on('input', function() {
            var query = $(this).val();

            if (query.length > 0) {
                $.ajax({
                    url: "{{ route('words.autocomplete') }}",  // The route for autocomplete
                    type: "GET",
                    data: { query: query },
                    success: function(data) {
                        var results = data.results;
                        $('#search-results').empty().show();
                        if (results.length > 0) {
                            results.forEach(function(result) {
                                $('#search-results').append('<li class="list-group-item"><a href="/words/' + result.slug + '">' + result.word + '</a></li>');
                            });
                        } else {
                            $('#search-results').append('<li class="list-group-item">No results found</li>');
                        }
                    }
                });
            } else {
                $('#search-results').empty().hide();
            }
        });

        // Hide search results when clicking outside
        $(document).click(function(e) {
            if (!$(e.target).closest('#search-input').length) {
                $('#search-results').empty().hide();
            }
        });
    });
</script>

@endsection
