<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Taheiya RCP regtrhegtretgerterret</title>

        <link rel="icon" type="image/png" href="{{ getCompanyLogo() }}" />


        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        {{-- @vite(['resources/css/app.css', 'resources/js/app.js']) --}}
{{--        <link rel="preload" as="style" href="https://rcp.taheiya.sa/public/build/assets/app-jksCLzLZ.css" />--}}
{{--        <link rel="modulepreload" href="https://rcp.taheiya.sa/public/build/assets/app-ecWDpuvf.js" />--}}
{{--        <link rel="stylesheet" href="https://rcp.taheiya.sa/public/build/assets/app-jksCLzLZ.css" data-navigate-track="reload" />--}}
{{--        <script type="module" src="https://rcp.taheiya.sa/public/build/assets/app-ecWDpuvf.js" data-navigate-track="reload"></script>--}}
{{--        <link rel='stylesheet' type='text/css' property='stylesheet' href='https://rcp.taheiya.sa/public/_debugbar/assets/stylesheets?v=1708004948&theme=auto' data-turbolinks-eval='false' data-turbo-eval='false'><script src='//rcp.taheiya.sa/_debugbar/assets/javascript?v=1708004948' data-turbolinks-eval='false' data-turbo-eval='false'></script><script data-turbo-eval='false'>jQuery.noConflict(true);</script>--}}

        <link rel="stylesheet" href="{{ asset('build/assets/app.css') }}">
        <script type="module" src="{{ asset('build/assets/app.js') }}"></script>

{{--        <link rel="stylesheet" href="{{ asset('build/assets/222app-bdDrzi0r.css') }}">--}}
{{--        <script src="{{ asset('build/assets/222app-ecWDpuvf.js') }}"></script>--}}

        @yield('styles')
    </head>
    <body class="font-sans antialiased" lang="{{ app()->getLocale() == 'ar' ? 'Ar' : 'En'  }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr'  }}">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            <livewire:layout.navigation />

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
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
