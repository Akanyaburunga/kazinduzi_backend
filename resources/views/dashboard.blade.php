@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="container">
    <h1>Welcome, {{ $user->name }}</h1>
    <p>Here are your contributions:</p>

    @if($contributions->isEmpty())
        <p>You haven't added any words yet. Start contributing <a href="{{ route('words.create') }}">here</a>.</p>
    @else
        <ul class="list-group">
        @foreach($contributions as $word)
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <a href="{{ route('words.show', $word) }}">
                    <strong>{{ $word->word }}</strong>
                </a>: {{ $word->meaning }}
                <br>
                <small>Added on {{ $word->created_at->format('F j, Y') }}</small>
            </div>
            <div>
                <a href="{{ route('words.edit', $word) }}" class="btn btn-sm btn-warning">Edit</a>
                <!-- Delete Button triggers modal -->
                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" 
                        data-id="{{ $word->id }}" data-word="{{ $word->word }}">
                    Delete
                </button>
            </div>
        </li>
    @endforeach
        </ul>

        <div class="mt-3">
            {{ $contributions->links() }} <!-- Pagination links -->
        </div>
    @endif
</div>
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete <strong id="modal-word"></strong>?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>


<script>
    const deleteModal = document.getElementById('deleteModal');
    const deleteForm = document.getElementById('deleteForm');
    const modalWord = document.getElementById('modal-word');

    deleteModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget; // Button that triggered the modal
        const wordId = button.getAttribute('data-id'); // Extract word ID
        const wordName = button.getAttribute('data-word'); // Extract word name

        // Update modal content
        modalWord.textContent = wordName;
        deleteForm.action = `/words/${wordId}`;
    });
</script>

@endsection

