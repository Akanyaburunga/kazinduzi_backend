@extends('layouts.app')

@section('title', 'Create a word')

@section('content')
<div class="container">

    <div class="row justify-content-center my-5">
            <div class="col-lg-8 text-center">
                <h3 class="display-6 text-primary">Terera ijambo rishasha!</h3>
            </div>
    </div>

    <form action="{{ route('words.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="word" class="form-label">Ijambo</label>
            <input type="text" name="word" value="{{ request('word') }}" id="word" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="type" class="form-label">Word Type</label>
            <select class="form-select" name="type" id="type" required>
                <option value="" disabled selected>-- Select Type --</option>
                <option value="izina">Izina</option>
                <option value="irivuga">Irivuga</option>
                <option value="ingereka">Ingereka</option>
                <option value="ingereka-zina">Ingereka-zina</option>
                <option value="ingereka-nsigarirazina">Ingereka-nsigarirazina</option>
                <option value="Insigarirazina">Insigarirazina</option>
                <option value="insigarirazina-ngereka">insigarirazina-ngereka</option>
                <option value="inshozi">Inshozi</option>
                <option value="intumbuzi">Intumbuzi</option>
                <option value="indongorazina">Indongorazina</option>
                <option value="sahwanya">Sahwanya</option>
                <option value="gakumbanya">Gakumbanya</option>
                <option value="gatangazi">Gatangazi</option>
                <option value="inkebuzo">Inkebuzo</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="meaning" class="form-label">Insiguro</label>
            <textarea name="meaning" id="meaning" class="form-control" rows="4" required></textarea>
        </div>
        <button type="submit" class="btn btn-success">Ndaterereye 🤩</button>
    </form>
</div>
@endsection
