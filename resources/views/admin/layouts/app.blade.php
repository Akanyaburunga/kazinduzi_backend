<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Admin') | Kazinduzi</title>

        <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}">
    </head>
    <body class="bg-gray-100 font-sans antialiased">
        <div id="admin-app">
            @yield('content')
        </div>

        @vite(['resources/js/admin/app.js'])
    </body>
</html>
