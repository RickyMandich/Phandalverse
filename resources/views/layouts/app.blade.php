<!doctype html>
<html data-bs-theme="dark" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v={{ time() }}">

    <title>@yield('title', config('app.name'))</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">
    
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- inclusion -->
    @yield('include')

    <style>
        .bg-custom-light{
            background-color: #555555;
        }
        nav{
            z-index: 1021;
        }
        a{
            text-decoration: none;
            color: inherit;
        }
        /* Fix per conflitto Tailwind/Bootstrap - forza visibilità pulsanti navbar */
        .navbar-toggler {
            display: inline-block !important;
            border: 1px solid rgba(255,255,255,.1) !important;
            padding: 0.25rem 0.75rem !important;
        }
        .navbar-toggler-icon {
            display: inline-block !important;
            width: 1.5em !important;
            height: 1.5em !important;
            vertical-align: middle !important;
            background-image: var(--bs-navbar-toggler-icon-bg) !important;
            background-repeat: no-repeat !important;
            background-position: center !important;
            background-size: 100% !important;
        }
        button {
            all: revert;
        }
    </style>

    @yield('style')
</head>
<body>
    <div id="app" class="d-flex flex-column min-vh-100">
        <nav class="navbar navbar-expand-md navbar-dark bg-dark shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}">
                    {{ config('app.name', 'Laravel') }}
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto">

                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Authentication Links -->
                        @guest
                            @if (Route::has('login'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                                </li>
                            @endif

                            @if (Route::has('register'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                                </li>
                            @endif
                        @else
                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    {{ Auth::user()->name }}
                                </a>

                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                        {{ __('Logout') }}
                                    </a>

                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>


        <main class="py-4 flex-grow-1">
            <div class="container content @yield('content-class')">
                @yield('content')
            </div>
        </main>

        <footer class="bg-custom-light mt-auto pt-2">
            <div class="container">
                <div class="row">
                    <div class="col-12 text-center">
                        <p>
                            {{ env('APP_VERSION') }}
                            Created by 
                            <small class="text-muted text-uppercase">
                                <a href="https://github.com/MandichRiccardoITS" target="_blank" rel="author noopener noreferrer">Mandich Riccardo</a>
                            </small>
                            <br>
                            with
                            <small class="text-muted text-uppercase">
                                <a href="https://laravel.com/docs/12.x" target="_blank" rel="noopener noreferrer">laravel</a>
                            </small>
                        </p>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    @yield('script')
</body>
</html>