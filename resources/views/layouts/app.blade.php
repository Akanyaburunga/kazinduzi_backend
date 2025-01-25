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


        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">

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

        <!-- Page Content -->
        <main>
            @yield('content')
        </main>
        </div>
    </body>
</html>
