@extends('layouts.app') <!-- Extends your main layout -->

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header text-center">{{ __('Login') }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="form-group mb-3">
                            <label for="email">{{ __('Email Address') }}</label>
                            <input id="email" type="email" class="form-control" name="email" required autofocus>
                        </div>

                        <div class="form-group mb-3">
                            <label for="password">{{ __('Password') }}</label>
                            <input id="password" type="password" class="form-control" name="password" required>
                        </div>

                        <div class="form-group mb-3 text-center">
                            <button type="submit" class="btn btn-primary w-100">
                                {{ __('Login') }}
                            </button>
                        </div>

                        <div class="text-center">
                            <a href="{{ route('register') }}">{{ __('Don\'t have an account? Register here') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
