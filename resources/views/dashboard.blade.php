@extends('layouts.app')

@section('title', 'Urugănda')

@section('content')
<div class="container my-5" style="max-width: 60%;">
    <div class="text-center mb-5">
        <h1 class="display-4">Ikâzé, {{ $user->name }}</h1>
        <p class="text-muted">Tunganya intererano zawe n'abatumire.</p>
    </div>

    <div class="card shadow-sm mb-5">
        <div class="card-header bg-primary text-white">
            <h3>Ivyo waterereye</h3>
        </div>
        <div class="card-body">
            @if($contributions->isEmpty())
                <p class="text-center">Nta ntererano uratanga🥲 Terera ijambo rya mbere🤗 <a href="{{ route('words.create') }}" class="text-primary">here</a>.</p>
            @else
                <ul class="list-group">
                    @foreach($contributions as $word)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <a href="{{ route('words.show', $word) }}" class="text-decoration-none">
                                    <strong>{{ $word->word }}</strong>
                                </a>: {{ $word->meaning }}
                                <br>
                                <small class="text-muted">Ryatererewe ku wa {{ $word->created_at->format('F j, Y') }}</small>
                            </div>
                            <div>
                                <a href="{{ route('words.edit', $word) }}" class="btn btn-sm btn-outline-warning">Hubūra</a>
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                        data-id="{{ $word->id }}" data-word="{{ $word->word }}" disabled>
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
            <h3>Abo watumiye</h3>
        </div>
        <div class="card-body">
            @if ($referrals->isEmpty())
                <p class="text-center">Ntabo uratumira. Sabikanya ubutumire mu bagenzi!</p>
            @else
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Izina</th>
                            <th>Uko biri</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($referrals as $referral)
                            <tr>
                                <td>{{ $referral->name }}</td>
                                <td>
                                    @if ($referral->email_verified_at)
                                        <span class="badge bg-success">Yasūzumwe</span>
                                    @else
                                        <span class="badge bg-danger">Ntarasūzumwa</span>
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
            <h3>Kode y'ubutumire</h3>
        </div>
        
        <div class="share-buttons text-center mt-4">
    <p class="fw-bold">Sabikanya agahora k'ubutumire:</p>

    <!-- Referral Link Input with Copy Button -->
    <div class="input-group mb-3">
        <input type="text" id="referralLink" class="form-control" value="{{ url('/register?ref=' . Auth::user()->referral_code) }}" readonly>
        <button class="btn btn-secondary" onclick="copyReferralLink()">
            <i class="fas fa-copy"></i> Abūra
        </button>
    </div>

    <!-- Social Media Share Buttons -->
    <div class="d-flex flex-wrap justify-content-center gap-2">

        <!-- WhatsApp -->
        <a href="https://api.whatsapp.com/send?text=Join%20this%20awesome%20platform:%20{{ urlencode(url('/register?ref=' . Auth::user()->referral_code)) }}" 
           target="_blank" class="btn btn-success">
            <i class="fab fa-whatsapp"></i> Sabikanya kuri WhatsApp
        </a>

        <!-- Facebook -->
        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url('/register?ref=' . Auth::user()->referral_code)) }}" 
           target="_blank" class="btn btn-primary">
            <i class="fab fa-facebook"></i> Sabikanya kuri Facebook
        </a>

        <!-- Twitter (X) -->
        <a href="https://twitter.com/intent/tweet?text=Join%20this%20awesome%20platform%20{{ urlencode(url('/register?ref=' . Auth::user()->referral_code)) }}" 
           target="_blank" class="btn btn-info text-white">
            <i class="fab fa-x-twitter"></i> Sabikanya kuri X
        </a>

        <!-- LinkedIn -->
        <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(url('/register?ref=' . Auth::user()->referral_code)) }}" 
           target="_blank" class="btn btn-primary" style="background-color: #0077B5; border-color: #0077B5;">
            <i class="fab fa-linkedin"></i> Sabikanya kuri LinkedIn
        </a>
    </div>
</div>

    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Emeza</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Koko wipfuza gufuta ? <strong id="wordToDelete"></strong>?
            </div>
            <div class="modal-footer">
                <form id="deleteForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ndabivuyemwo</button>
                    <button type="submit" class="btn btn-danger">Futa</button>
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
