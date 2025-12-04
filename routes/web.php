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

// ========== ADMIN ROUTES (solo amministratori) ==========
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    // Visualizzazione log
    Route::get('/logs', [LogsController::class, 'index'])->name('admin.logs');

    // Gestione errori
    Route::get('/errors', [AdminController::class, 'errors'])->name('admin.errors');
    Route::get('/errors/{error}', [AdminController::class, 'showError'])->name('admin.errors.show');
    Route::patch('/errors/{error}', [AdminController::class, 'updateError'])->name('admin.errors.update');
    Route::get('/errors/quick-action/{error}/{action}', [AdminController::class, 'quickActionError'])->name('admin.errors.quick-action');

    // Impostazioni Vault
    Route::post('/vault/set-default-view', [VaultController::class, 'setDefaultView'])->name('admin.vault.setDefaultView');
});

// ========== JOB ROUTES ==========

// Route per il processore email (protetta da JOB_TOKEN)
Route::get("/job/ProcessEmailQueue", [JobController::class, 'processEmailQueue'])
    ->name("job.processEmailQueue");

Route::fallback(function () {
    return view('errors.404');
});