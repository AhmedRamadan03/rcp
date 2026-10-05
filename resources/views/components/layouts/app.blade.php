<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? 'RCP' }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <link rel="icon" type="image/png" href="{{ getCompanyLogo() }}"/>


{{--    @vite(['resources/css/app.css', 'resources/js/app.js'])--}}
    {{-- <link rel="preload" as="style" href="hattps://rcp.taheiya.sa/public/build/assets/app-jksCLzLZ.css" />
    <link rel="modulepreload" href="https://rcp.taheiya.sa/public/build/assets/app-ecWDpuvf.js" />
    <link rel="stylesheet" href="https://rcp.taheiya.sa/public/build/assets/pp-jksCLzLZ.css" data-navigate-track="reload" />
    <script type="module" src="https://rcp.taheiya.sa/public/build/assets/app-ecWDpuvf.js" data-navigate-track="reload"></script>
    <link rel='stylesheet' type='text/css' property='stylesheet' href='https://rcp.taheiya.sa/public/_debugbar/assets/stylesheets?v=1708004948&theme=auto' data-turbolinks-eval='false' data-turbo-eval='false'><script src='//rcp.taheiya.sa/_debugbar/assets/javascript?v=1708004948' data-turbolinks-eval='false' data-turbo-eval='false'></script><script data-turbo-eval='false'>jQuery.noConflict(true);</script> --}}

    @yield('styles')

    <script type="module" src="{{ asset('build/assets/app.js') }}"></script>

    <link rel="stylesheet" href="{{ asset('build/assets/app.css') }}">

{{--    <link rel="stylesheet" href="{{ asset('build/assets/app-bdDrzi0r.css') }}">--}}
{{--    <script src="{{ asset('build/assets/app.js') }}"></script>--}}

    <script src="/public/htmx.min.js"></script>

    <link href="https://unpkg.com/filepond@^4/dist/filepond.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com/3.4.4" rel="stylesheet"></script>
    <style>
        body {
            padding: 0;
            margin: 0;
            height: 100vh;
            overflow: hidden;
        }

        main {
            padding: 0;
            margin: 0;
            height: 100vh;
            overflow: hidden;
            display: grid;
            grid-template-columns: 200px 1fr;
        }

        .app {
            /* background-color: rgb(255, 255, 255); */
            padding: 0;
            margin: 0;
            height: 100vh;
            overflow: hidden;
            display: grid;
            grid-template-columns: 5fr 2fr;
        }

        nav {
            /* background-color: red !important; */
            height: 100vh;
            display: grid;
            grid-template-rows: 60px 1fr 300px;
        }

        .content {
            /* background-color: rgb(195, 238, 164) !important; */
            height: 100vh;
        }

        .content header {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .content header .title {
            /* background-color: rgba(14, 152, 16, 0.844) !important; */
            height: 55px;
            /* border-radius: 10px 10px 0px 0px; */
        }

        .content footer .options {
            /* background-color: rgba(14, 152, 16, 0.844) !important; */
            height: 55px;
            /* border-radius: 10px 10px 0px 0px; */
        }

        .side-content {
            height: calc(100vh - 200px);
            overflow-y: scroll;
            overflow-x: hidden;
            overflow: auto !important;
        }

        .main-content {
            height: calc(100vh - 200px);
            overflow-y: scroll;
            overflow-x: scroll;
        }

        .side {
            /* background-color: rgb(164, 206, 238) !important; */
            height: 110vh;
        }

        .side header {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .side header .title {
            /* background-color: rgba(14, 152, 16, 0.844) !important; */
            height: 55px;
            /* border-radius: 10px 10px 0px 0px; */
        }

        .side footer .options {
            /* background-color: rgba(14, 152, 16, 0.844) !important; */
            height: 55px;
            /* border-radius: 10px 10px 0px 0px; */
        }

        @media screen and (max-width: 992px) {
            main {
                display: flex;
                flex-direction: column;
            }

            nav {
                /* background-color: green !important; */
                height: 60px
            }

            nav {
                /* background-color: green !important; */
                height: 60px
            }
        }
    </style>

</head>

<body class="font-sans antialiased" lang="{{ app()->getLocale() == 'ar' ? 'Ar' : 'En'  }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr'  }}">
    <main class="bg-gray-100">
        <x-nav />
        {{ $slot }}

    </main>

    <script src="https://unpkg.com/filepond@^4/dist/filepond.js"></script>

    @yield('scripts')
</body>

</html>
