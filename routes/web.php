<?php

use App\Http\Controllers\VaultController;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\JobController;

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

// ========== ADMIN ROUTES ==========

// Visualizzazione log
Route::get('/admin/logs', [LogsController::class, 'index'])
    ->name('admin.logs')
    ->middleware('auth');

// Gestione errori
Route::get('/admin/errors', [AdminController::class, 'errors'])
    ->name('admin.errors')
    ->middleware('auth');

Route::get('/admin/errors/{error}', [AdminController::class, 'showError'])
    ->name('admin.errors.show')
    ->middleware('auth');

Route::patch('/admin/errors/{error}', [AdminController::class, 'updateError'])
    ->name('admin.errors.update')
    ->middleware('auth');

Route::get('/admin/errors/quick-action/{error}/{action}', [AdminController::class, 'quickActionError'])
    ->name('admin.errors.quick-action')
    ->middleware('auth');

// ========== JOB ROUTES ==========

// Route per il processore email (protetta da JOB_TOKEN)
Route::get("/job/ProcessEmailQueue", [JobController::class, 'processEmailQueue'])
    ->name("job.processEmailQueue");

Route::fallback(function () {
    return view('errors.404');
});