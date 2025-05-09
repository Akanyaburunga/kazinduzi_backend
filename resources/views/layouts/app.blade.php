<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="{{ e($__env->yieldContent('meta-description', "Kazinduzi ni urubuga rwo kwiyibutsa amajambo n'insiguro yayo mu Kirundi")) }}">

        <!-- Open Graph -->
        <meta property="og:title" content="@yield('title', 'Kazinduzi')" />
        <meta property="og:description"  content="{{ e($__env->yieldContent('meta-description', "Kazinduzi ni urubuga rwo kwiyibutsa amajambo n'insiguro yayo mu Kirundi")) }}" />
        <meta property="og:url" content="{{ url()->current() }}" />
        <meta property="og:type" content="website" />
        <meta property="og:image" content="@yield('meta-image', asset('default-image.jpg'))" />

        <!-- Favicon -->
        <link rel="icon" href="{{ asset('images/icons/favicon.ico') }}" type="image/x-icon">
        <link rel="icon" href="{{ asset('images/icons/favicon-32x32.png') }}" sizes="32x32" type="image/png">
        <link rel="icon" href="{{ asset('images/icons/favicon.svg') }}" type="image/svg+xml">


        <!-- Twitter -->
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="@yield('title', 'Kazinduzi')" />
        <meta name="twitter:description"  content="{{ e($__env->yieldContent('meta-description', "Kazinduzi ni urubuga rwo kwiyibutsa amajambo n'insiguro yayo mu Kirundi")) }}" />
        <meta name="twitter:image" content="@yield('meta-image', asset('default-image.jpg'))" />


        <title>@yield('title') | Kazinduzi</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Styles -->
        <link rel="stylesheet" href="{{ asset('css/bootstrap/css/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}">
        <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
        <link rel="stylesheet" href="https://unpkg.com/easymde/dist/easymde.min.css">

        <!-- Include jQuery locally -->
        <script src="{{ asset('js/jquery-3.6.0.min.js') }}"></script>
        <script src="https://unpkg.com/easymde/dist/easymde.min.js"></script>
        <script src="{{ asset('js/custom.js') }}"></script>
        <script src="{{ asset('js/bootstrap/js/bootstrap.min.js') }}"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        @if (config('app.env') === 'production') 
        <!-- Google tag (gtag.js) -->
            <!-- Google tag (gtag.js) -->
            <script async src="https://www.googletagmanager.com/gtag/js?id=G-BKG0NF15BD"></script>
            <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());

            gtag('config', 'G-BKG0NF15BD');
            </script>
        @endif
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
    
@include('layouts.footer')
    </body>
</html>
