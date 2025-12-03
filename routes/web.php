<?php

use App\Http\Controllers\VaultController;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Auth::routes();

Route::get('/', function () {
    return view('welcome');
})->name('index');

Route::get('/vault/{note?}', [VaultController::class, 'show'])
    ->where('note', '.*')
    ->name('vault.show');

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('dashboard');

Route::fallback(function () {
    return view('errors.404');
});