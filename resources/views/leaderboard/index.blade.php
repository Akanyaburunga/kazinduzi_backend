@extends('layouts.app')

@section('title', 'Leaderboard')

@section('content')
<div class="container">
    <h1 class="text-center mb-4">🏆 Leaderboard</h1>

    <!-- Filter Form with predefined options -->
    <div class="d-flex justify-content-center mb-4">
        <form method="GET" action="{{ route('leaderboard.index') }}">
            <select name="filter" class="form-control" onchange="this.form.submit()">
                <option value="today" {{ $filter == 'today' ? 'selected' : '' }}>Today</option>
                <option value="this_week" {{ $filter == 'this_week' ? 'selected' : '' }}>This Week</option>
                <option value="this_month" {{ $filter == 'this_month' ? 'selected' : '' }}>This Month</option>
                <option value="this_year" {{ $filter == 'this_year' ? 'selected' : '' }}>This Year</option>
                <option value="all_time" {{ $filter == 'all_time' ? 'selected' : '' }}>All Time</option>
            </select>
        </form>
    </div>

    <!-- Leaderboard Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover text-center">
            <thead class="thead-dark">
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Email</th>
                    <th>Total Reputation</th>
                    <th>Total Words</th>
                    <th>Total Meanings</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $index => $user)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td><a href="{{ route('users.show', $user->id) }}">{{ $user->name }}</a></td>
                        <td>{{ $user->email }}</td>
                        <td><strong>{{ $user->total_reputation }}</strong></td>
                        <td>{{ $user->total_words }}</td>
                        <td>{{ $user->total_meanings }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-muted">No users found for the selected filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center">
        {{ $users->links() }}
    </div>
</div>
@endsection
