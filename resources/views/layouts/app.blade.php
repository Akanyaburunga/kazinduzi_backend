<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="@yield('meta-description', 'Kazinduzi ni urubuga')">

        <!-- Open Graph -->
        <meta property="og:title" content="@yield('title', 'Kazinduzi')" />
        <meta property="og:description" content="@yield('meta-description', 'Default description for the Kazinduzi')" />
        <meta property="og:url" content="{{ url()->current() }}" />
        <meta property="og:type" content="website" />
        <meta property="og:image" content="@yield('meta-image', asset('default-image.jpg'))" />

        <!-- Twitter -->
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="@yield('title', 'Kazinduzi')" />
        <meta name="twitter:description" content="@yield('meta-description', 'Default description for the Kazinduzi')" />
        <meta name="twitter:image" content="@yield('meta-image', asset('default-image.jpg'))" />


        <title>{{ config('app.name', 'Kazinduzi') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Styles -->
        <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="container my-5">
            <div class="row justify-content-center">

            <!-- Page Content -->
            <div class="col-md-8 col-lg-8">
            @if (session('info'))
        <div class="alert alert-info">
            {{ session('info') }}
        </div>
        @endif
        @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        @include('layouts.navigation')

        <!-- Page Heading -->
        @if (isset($header))
            <header class="bg-white dark:bg-gray-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
            </header>
        @endif
                @yield('content')
            </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    
        <script>
    document.addEventListener("DOMContentLoaded", function () {
        const toggleButton = document.getElementById("dark-mode-toggle");
        const body = document.body;

        // Check if dark mode was previously enabled
        if (localStorage.getItem("dark-mode") === "enabled") {
            body.classList.add("dark-mode");
            toggleButton.textContent = "☀️ Light Mode";
        }

        toggleButton.addEventListener("click", function () {
            if (body.classList.contains("dark-mode")) {
                body.classList.remove("dark-mode");
                localStorage.setItem("dark-mode", "disabled");
                toggleButton.textContent = "🌙 Dark Mode";
            } else {
                body.classList.add("dark-mode");
                localStorage.setItem("dark-mode", "enabled");
                toggleButton.textContent = "☀️ Light Mode";
            }
        });
    });
</script>


    </body>
</html>
