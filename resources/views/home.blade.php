@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Welcome to Kazinduzi!</h1>

    <!-- Recent Contributions -->
    <section class="my-5">
        <h2>Recent Contributions</h2>
        @if ($recentWords->isEmpty())
            <p>No recent contributions found.</p>
        @else
            <ul class="list-group">
                @foreach ($recentWords as $word)
                <li class="list-group-item">
                    <a href="{{ route('words.show', $word) }}">
                        <strong>{{ $word->word }}</strong>
                    </a>: {{ $word->meaning }}
                    <br>
                    <small>By {{ $word->user->name }} on {{ $word->created_at->format('F j, Y') }}</small>
                </li>
                @endforeach
            </ul>

            <!-- Pagination Links -->
            <div class="mt-3">
                {{ $recentWords->links() }}
            </div>
        @endif
    </section>

    <!-- Top Contributors -->
    <section class="my-5">
        <h2>Top Contributors</h2>
        @if ($topContributors->isEmpty())
            <p>No contributors found.</p>
        @else
            <ul class="list-group">
                @foreach ($topContributors as $user)
                    <li class="list-group-item">
                        {{ $user->name }} - {{ $user->words_count }} contributions
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
