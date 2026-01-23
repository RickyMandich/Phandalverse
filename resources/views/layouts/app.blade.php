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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/scss/app.scss', 'resources/js/app.js'])

    <!-- inclusion -->
    @yield('include')
</head>

@yield('style')

<body>
    <div id="app" class="d-flex flex-column justify-content-between min-vh-100">
        <nav class="navbar navbar-expand-md shadow-sm bg-secondary">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}">
                    {{ config('app.name', 'Laravel') }}
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('vault.show') }}">
                                <i class="bi bi-folder2-open"></i> {{ __('Vault') }}
                            </a>
                        </li>
                    </ul>

                    <!-- Search Form -->
                    <form class="d-flex mx-auto col-12 col-lg-6 my-2 my-lg-0" action="{{ route('vault.search') }}"
                        method="GET">
                        <div class="input-group">
                            <input class="form-control bg-dark text-white border-secondary" type="search" name="q"
                                placeholder="Cerca nel Vault..." aria-label="Search" value="{{ request('q') }}">
                            <button class="btn btn-outline-warning" type="submit">
                                <i class="bi-search"></i>
                            </button>
                        </div>
                    </form>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Authentication Links -->
                        @guest
                            @if (Route::has('login'))
                                <li class="nav-item">
                                    <a class="nav-link"
                                        href="{{ route('login', ['from' => request()->fullUrl()]) }}">{{ __('Login') }}</a>
                                </li>
                            @endif

                            @if (Route::has('register'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                                </li>
                            @endif
                        @else
                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button"
                                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    {{ Auth::user()->name }}
                                </a>

                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    {{-- Sezione Admin (solo per amministratori) --}}
                                    @if(Auth::user()->isAdmin())
                                        <h6 class="dropdown-header">
                                            <i class="fas fa-shield-alt"></i> Admin
                                        </h6>
                                        <a class="dropdown-item" href="{{ route('admin.users') }}">
                                            <i class="bi bi-people"></i> Utenti
                                        </a>
                                        <a class="dropdown-item" href="{{ route('admin.reports') }}">
                                            📢 Segnalazioni
                                        </a>
                                        <a class="dropdown-item" href="{{ route('admin.statistics') }}">
                                            <i class="bi bi-graph-up"></i> Statistiche Accessi
                                        </a>
                                        <a class="dropdown-item" href="{{ route('admin.errors') }}">
                                            <i class="fas fa-exclamation-triangle"></i> Errori Sistema
                                        </a>
                                        <a class="dropdown-item" href="{{ route('admin.logs') }}">
                                            <i class="fas fa-file-alt"></i> Log
                                        </a>
                                        <div class="dropdown-divider"></div>
                                    @endif

                                    @if(Auth::user()->master)
                                        <h6 class="dropdown-header">
                                            <i class="bi bi-dice-6"></i> Dungeon Master
                                        </h6>
                                        <a class="dropdown-item" href="{{ route('dm.screen') }}">
                                            <i class="bi bi-person-badge"></i> DM Screen
                                        </a>
                                        <div class="dropdown-divider"></div>
                                    @endif

                                    <a class="dropdown-item" href="{{ route('dashboard') }}">
                                        <i class="bi bi-person-circle"></i> Il mio Profilo
                                    </a>

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

        <footer class="bg-secondary mt-auto pt-2">
            <div class="container">
                <div class="row">
                    <div class="col text-center">
                        <p class="mb-2">
                            <a href="{{ route('report.create', ['from' => url()->current()]) }}" class="text-warning">
                                📢 Segnala un problema
                            </a>
                        </p>
                        <p class="mb-1">
                            <span class="text-info">🕐 {{ now()->format('d/m/Y H:i:s') }}</span>
                        </p>
                        <p>
                            {{ env('APP_VERSION') }}
                            Created by
                            <small class="text-muted text-uppercase">
                                <a href="https://github.com/RickyMandich" target="_blank"
                                    rel="author noopener noreferrer">Mandich Riccardo</a>
                            </small>
                            <br>
                            with
                            <small class="text-muted text-uppercase">
                                <a href="https://laravel.com/docs/12.x" target="_blank"
                                    rel="noopener noreferrer">laravel</a>
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