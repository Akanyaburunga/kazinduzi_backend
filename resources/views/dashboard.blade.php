@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="container my-5" style="max-width: 60%;">
    <div class="text-center mb-5">
        <h1 class="display-4">Welcome, {{ $user->name }}</h1>
        <p class="text-muted">Manage your contributions and referrals below.</p>
    </div>

    <div class="card shadow-sm mb-5">
        <div class="card-header bg-primary text-white">
            <h3>Your Contributions</h3>
        </div>
        <div class="card-body">
            @if($contributions->isEmpty())
                <p class="text-center">You haven't added any words yet. Start contributing <a href="{{ route('words.create') }}" class="text-primary">here</a>.</p>
            @else
                <ul class="list-group">
                    @foreach($contributions as $word)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <a href="{{ route('words.show', $word) }}" class="text-decoration-none">
                                    <strong>{{ $word->word }}</strong>
                                </a>: {{ $word->meaning }}
                                <br>
                                <small class="text-muted">Added on {{ $word->created_at->format('F j, Y') }}</small>
                            </div>
                            <div>
                                <a href="{{ route('words.edit', $word) }}" class="btn btn-sm btn-outline-warning">Edit</a>
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                        data-id="{{ $word->id }}" data-word="{{ $word->word }}">
                                    Delete
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-3">
                    {{ $contributions->links() }}
                </div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm mb-5">
        <div class="card-header bg-success text-white">
            <h3>Your Referrals</h3>
        </div>
        <div class="card-body">
            @if ($referrals->isEmpty())
                <p class="text-center">No referrals yet. Share your referral code to invite others!</p>
            @else
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($referrals as $referral)
                            <tr>
                                <td>{{ $referral->name }}</td>
                                <td>
                                    @if ($referral->email_verified_at)
                                        <span class="badge bg-success">Verified</span>
                                    @else
                                        <span class="badge bg-danger">Not Verified</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
            <h3>Your Referral Code</h3>
        </div>
        <div class="card-body text-center">
            <p>Share this code to invite others:</p>
            <h4><strong>{{ $user->referral_code }}</strong></h4>
            <p>Referral Link:</p>
            <a href="{{ url('/register?ref=' . $user->referral_code) }}" class="text-decoration-none">{{ url('/register?ref=' . $user->referral_code) }}</a>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete <strong id="wordToDelete"></strong>?
            </div>
            <div class="modal-footer">
                <form id="deleteForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const deleteModal = document.getElementById('deleteModal');
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const wordId = button.getAttribute('data-id');
            const wordName = button.getAttribute('data-word');
            
            const deleteForm = document.getElementById('deleteForm');
            deleteForm.action = `/words/${wordId}`;
            
            const wordToDelete = document.getElementById('wordToDelete');
            wordToDelete.textContent = wordName;
        });
    });
</script>
@endsection
