<?php

use Illuminate\Support\Facades\Route;

Auth::routes();

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test', function(){
    return view('layouts.app');
});

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');