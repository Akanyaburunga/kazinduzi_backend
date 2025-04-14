@extends('layouts.app') <!-- Extends your main layout -->

@section('title', 'Iyandikishe')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header text-center">{{ __('Iyandikishe') }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="form-group mb-3">
                            <label for="email">{{ __('Imeyile') }}</label>
                            <input id="email" type="email" class="form-control" name="email" required autofocus>
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div class="form-group mb-3">
                            <label for="password">{{ __('Icandiko-kabanga') }}</label>
                            <input id="password" type="password" class="form-control" name="password" required>
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <div class="form-group mb-3 text-center">
                            <button type="submit" class="btn btn-primary w-100">
                                {{ __('Injira') }}
                            </button>
                        </div>

                        <div class="text-center">
                            <a href="{{ route('register') }}">{{ __('Nta konte urugurura kuri runo rubuga? Iyandikishe hano') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
