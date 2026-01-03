<?php

use App\Http\Controllers\VaultController;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TestController;
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

// ========== PROFILO UTENTE (autenticato) ==========
Route::middleware(['auth'])->group(function () {
    Route::patch('/profile', [App\Http\Controllers\HomeController::class, 'updateProfile'])->name('profile.update');
    Route::patch('/profile/password', [App\Http\Controllers\HomeController::class, 'updatePassword'])->name('profile.password');
});

// ========== SEGNALAZIONI (pubbliche) ==========
Route::get('/report', [ReportController::class, 'create'])->name('report.create');
Route::post('/report', [ReportController::class, 'store'])->name('report.store');

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

    // Gestione segnalazioni
    Route::get('/reports', [ReportController::class, 'index'])->name('admin.reports');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('admin.reports.show');
    Route::patch('/reports/{report}', [ReportController::class, 'update'])->name('admin.reports.update');

    // Gestione utenti
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/users/create', [AdminController::class, 'createUser'])->name('admin.users.create');
    Route::post('/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::get('/users/{user}/edit', [AdminController::class, 'editUser'])->name('admin.users.edit');
    Route::patch('/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::patch('/users/{user}/password', [AdminController::class, 'updateUserPassword'])->name('admin.users.password');
    Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('admin.users.delete');
});

// ========== JOB ROUTES ==========

// Route per il processore email (protetta da JOB_TOKEN)
Route::get("/job/ProcessEmailQueue", [JobController::class, 'processEmailQueue'])
    ->name("job.processEmailQueue");

Route::fallback(function () {
    return view('errors.404');
});

Route::get('/test-hash', [TestController::class, 'testHash'])->name('test.hash');