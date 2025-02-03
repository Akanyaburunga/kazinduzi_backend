@extends('layouts.app')

@section('title', 'Leaderboard')

@section('content')
<div class="container my-4">
    <h1 class="text-center display-6 text-primary">🏆 Our leading contributors</h1>
    <div class="table-responsive">
        <table class="table table-hover leaderboard-table">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Reputation</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($topUsers as $index => $user)
                    <tr class="{{ $index == 0 ? 'gold' : ($index == 1 ? 'silver' : ($index == 2 ? 'bronze' : '')) }}">
                        <td>
                            <strong>
                                @if($index == 0) 🥇 @elseif($index == 1) 🥈 @elseif($index == 2) 🥉 @else #{{ $index + 1 }} @endif
                            </strong>
                        </td>
                        <td>
                        <img src="https://www.gravatar.com/avatar/{{ md5(strtolower(trim($user->email))) }}?s=120&d=mp" 
                        class="rounded-circle mb-3" alt="User Avatar">
                            <a href="{{ route('users.show', $user->id) }}" class="text-decoration-none">{{ $user->name }}</a>
                        </td>
                        <td><strong>{{ $user->reputation }}</strong> points</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<style>
    .leaderboard-table {
        width: 100%;
        max-width: 800px;
        margin: auto;
        background: #fff;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .table-dark {
        background-color: #222;
        color: white;
    }

    .avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-right: 10px;
    }

    .gold { background: #FFD700; color: black; }
    .silver { background: #C0C0C0; color: black; }
    .bronze { background: #CD7F32; color: black; }

    tr.gold td, tr.silver td, tr.bronze td {
        font-weight: bold;
    }

    tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.05);
    }
</style>
@endsection
