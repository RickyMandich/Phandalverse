<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthMacroServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::macro('admin', function () {
            if (Auth::check()) {
                return (bool) Auth::user()->isAdmin();
            }
            return false;
        });

        Auth::macro('isAdmin', function () {
            return Auth::admin();
        });

        Auth::macro('isMaster', function () {
            if (Auth::check()) {
                return (bool) Auth::user()->isMaster();
            }
            return false;
        });

        Auth::macro('isMasterUtils', function () {
            if (Auth::check()) {
                return (bool) Auth::user()->isMasterUtils();
            }
            return false;
        });

        Auth::macro('getMaster', function () {
            if (Auth::check()) {
                return (bool) Auth::user()->master;
            }
            return false;
        });

        Auth::macro('getName', function () {
            if (Auth::check()) {
                return Auth::user()->name;
            }
            return null;
        });
    }
}

