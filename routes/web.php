<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Auth::routes();

Route::get('/test', function () {
    return view('welcome');
})->name('index');

Route::get('/', function(){
    return Artisan::command("route:list");
})->name('test');

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');