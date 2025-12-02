<?php

use Illuminate\Support\Facades\Route;

Auth::routes();

Route::get('/', function () {
    return view('welcome');
})->name('index');

Route::get('/test', function(){
    return view('layouts.app');
})->name('test');

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('dashboard');