@extends('layouts.app')

@section('title', 'Leaderboard')

@section('content')
<div class="container mt-4">
    <h1 class="text-center mb-4">🏆 Leaderboard 🏆</h1>

    <!-- Filters -->
    <div class="d-flex justify-content-center mb-4">
        <div class="btn-group">
            <a href="{{ route('leaderboard.index', ['filter' => 'today']) }}" 
                class="btn btn-sm {{ $filter == 'today' ? 'btn-primary' : 'btn-outline-primary' }}">
                Today
            </a>
            <a href="{{ route('leaderboard.index', ['filter' => 'this_week']) }}" 
                class="btn btn-sm {{ $filter == 'this_week' ? 'btn-primary' : 'btn-outline-primary' }}">
                This Week
            </a>
            <a href="{{ route('leaderboard.index', ['filter' => 'this_month']) }}" 
                class="btn btn-sm {{ $filter == 'this_month' ? 'btn-primary' : 'btn-outline-primary' }}">
                This Month
            </a>
            <a href="{{ route('leaderboard.index', ['filter' => 'this_year']) }}" 
                class="btn btn-sm {{ $filter == 'this_year' ? 'btn-primary' : 'btn-outline-primary' }}">
                This Year
            </a>
            <a href="{{ route('leaderboard.index', ['filter' => 'all_time']) }}" 
                class="btn btn-sm {{ $filter == 'all_time' ? 'btn-primary' : 'btn-outline-primary' }}">
                All Time
            </a>
        </div>
    </div>

    <!-- Leaderboard Table -->
    <div class="table-responsive">
        <table class="table table-hover table-bordered text-center">
            <thead class="table-dark">
                <tr>
                    <th>Rank</th>
                    <th>Name</th>
                    <th>Reputation</th>
                    <th>Total Words</th>
                    <th>Total Meanings</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $index => $user)
                    <tr class="{{ $index == 0 ? 'table-warning' : ($index == 1 ? 'table-secondary' : ($index == 2 ? 'table-info' : '')) }}">
                        <td>
                            <span class="badge bg-{{ $index == 0 ? 'gold' : ($index == 1 ? 'silver' : ($index == 2 ? 'bronze' : 'dark') ) }} rounded-pill">
                                #{{ $index + 1 }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('users.show', $user->id) }}" class="fw-bold text-decoration-none text-dark">
                                {{ $user->name }}
                            </a>
                        </td>
                        <td><strong class="text-success">+{{ $user->total_reputation }}</strong></td>
                        <td>{{ $user->total_words }}</td>
                        <td>{{ $user->total_meanings }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-muted text-center py-3">
                            No users have earned reputation yet for this period.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center mt-3">
        {{ $users->links() }}
    </div>
</div>
@endsection
