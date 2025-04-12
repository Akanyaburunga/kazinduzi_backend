@extends('layouts.app')

@section('title', 'Uruganda ndangamuntu')

@section('content')
<div class="container">
    <h1>Uruganda ndangamuntu</h1>

    <div class="card p-4">
        <img src="{{ auth()->user()->getProfilePictureUrl() }}" alt="Profile Picture" 
             class="rounded-circle mb-3" width="150">

        <form action="{{ route('profile.change') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label">Hindura ishusho yawe</label>
                <input type="file" name="profile_picture" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary">Emeza ivyo wahinduye!</button>
        </form>
    </div>
</div>
@endsection
