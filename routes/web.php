<?php

use App\Http\Controllers\DmController;
use App\Http\Controllers\VaultController;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TelegramBotController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Auth::routes(['verify' => true]);

Route::get('/', function () {
    return view('welcome');
})->name('index');

// ========== VAULT ENTRYPOINT & MULTI-CAMPAIGN ROUTES ==========
Route::get('/vault', [VaultController::class, 'index'])->name('vault.index');

// Ricerca svincolata da una singola campagna: copre tutte quelle accessibili all'utente
Route::get('/vault/search', [VaultController::class, 'search'])->name('vault.search');

Route::prefix('vault/{campaign:folder_name}')->group(function () {
    Route::get('/changelog', [ChangelogController::class, 'index'])->name('vault.changelog.index');
    Route::get('/changelog/{version}', [ChangelogController::class, 'show'])->name('vault.changelog.show');
    Route::get('/{note?}', [VaultController::class, 'show'])
        ->where('note', '.*')
        ->name('vault.show');
});

Route::get('/api/vault/{campaign:folder_name}/{note?}', [VaultController::class, 'rawShow'])
    ->where('note', '.*')
    ->name('vault.raw');

Route::get('/home', [HomeController::class, 'index'])->name('dashboard')->middleware(['auth', 'verified']);

// ========== API PUBBLICA SESSIONI (Per visuale giocatori) ==========
Route::get('/dm/api/public/sessions/{share_code}', [DmController::class, 'publicLoadSession']);

// ========== PLAYER VIEW (Pubblica) ==========
Route::get('/dm/player', [DmController::class, 'playerIndex'])->name('dm.player.index');
Route::get('/dm/player/{share_code}', [DmController::class, 'playerView'])->name('dm.player');

// ========== PROFILO UTENTE (autenticato) ==========
Route::middleware(['auth', 'verified'])->group(function () {
    Route::patch('/profile', [HomeController::class, 'updateProfile'])->name('profile.update');
    Route::patch('/profile/password', [HomeController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/request-master', [HomeController::class, 'requestMasterUtils'])->name('profile.request_master');
});

// ========== SEGNALAZIONI (pubbliche) ==========
Route::get('/report', [ReportController::class, 'create'])->name('report.create');
Route::post('/report', [ReportController::class, 'store'])->name('report.store');

// ========== DM SCREEN (solo master o master_utils) ==========
Route::middleware(['auth', 'verified', 'master_utils'])->prefix('dm')->group(function () {
    Route::get('/', [DmController::class, 'index'])->name('dm.screen');
    Route::get('/powerfail', [DmController::class, 'powerfailScreen'])->name('dm.powerfail');
    Route::get('/manage', [DmController::class, 'manage'])->name('dm.manage');
    Route::get('/api/manage-data', [DmController::class, 'getManagementData'])->name('dm.api.manage-data');
    Route::get('/api/characters', [DmController::class, 'getCharacters'])->name('dm.api.characters.index');
    Route::post('/api/characters', [DmController::class, 'storeCharacter'])->name('dm.api.characters.store');
    Route::patch('/api/characters/{character}', [DmController::class, 'updateCharacter'])->name('dm.api.characters.update');
    Route::delete('/api/characters/{character}', [DmController::class, 'destroyCharacter'])->name('dm.api.characters.destroy');

    Route::get('/api/sessions', [DmController::class, 'getSessions'])->name('dm.api.sessions.index');
    Route::post('/api/sessions', [DmController::class, 'storeSession'])->name('dm.api.sessions.store');
    Route::get('/api/sessions/{session}', [DmController::class, 'loadSession'])->name('dm.api.sessions.load');
    Route::patch('/api/sessions/{session}', [DmController::class, 'updateSession'])->name('dm.api.sessions.update');
    Route::delete('/api/sessions/{session}', [DmController::class, 'destroySession'])->name('dm.api.sessions.destroy');

    Route::post('/api/session', [DmController::class, 'saveSession'])->name('dm.api.session.save');
    Route::get('/api/session', [DmController::class, 'legacyLoadSession'])->name('dm.api.session.load');

    Route::post('/api/render-stat-block', [DmController::class, 'renderStatBlock'])->name('dm.api.render-stat-block');
});

// ========== ADMIN ROUTES (solo amministratori) ==========
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
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
    Route::get('/reports/image/{path}', [ReportController::class, 'showImage'])
        ->where('path', '.*')
        ->name('admin.reports.image');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('admin.reports.show');
    Route::patch('/reports/{report}', [ReportController::class, 'update'])->name('admin.reports.update');

    // Gestione campagne
    Route::get('/campaigns', [AdminController::class, 'campaigns'])->name('admin.campaigns');
    Route::get('/campaigns/create', [AdminController::class, 'createCampaign'])->name('admin.campaigns.create');
    Route::post('/campaigns', [AdminController::class, 'storeCampaign'])->name('admin.campaigns.store');
    Route::get('/campaigns/{campaign}/edit', [AdminController::class, 'editCampaign'])->name('admin.campaigns.edit');
    Route::patch('/campaigns/{campaign}', [AdminController::class, 'updateCampaign'])->name('admin.campaigns.update');
    Route::delete('/campaigns/{campaign}', [AdminController::class, 'deleteCampaign'])->name('admin.campaigns.delete');

    // Gestione utenti
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/users/create', [AdminController::class, 'createUser'])->name('admin.users.create');
    Route::post('/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::get('/users/{user}/edit', [AdminController::class, 'editUser'])->name('admin.users.edit');
    Route::patch('/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::patch('/users/{user}/password', [AdminController::class, 'updateUserPassword'])->name('admin.users.password');
    Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('admin.users.delete');
    Route::post('/users/{user}/approve-master', [AdminController::class, 'approveMasterRequest'])->name('admin.users.approve_master');
    Route::post('/users/{user}/deny-master', [AdminController::class, 'denyMasterRequest'])->name('admin.users.deny_master');

    // Gestione gruppi di accesso
    Route::get('/access-groups', [AdminController::class, 'accessGroups'])->name('admin.access_groups');
    Route::get('/access-groups/create', [AdminController::class, 'createAccessGroup'])->name('admin.access_groups.create');
    Route::post('/access-groups', [AdminController::class, 'storeAccessGroup'])->name('admin.access_groups.store');
    Route::get('/access-groups/{group}/edit', [AdminController::class, 'editAccessGroup'])->name('admin.access_groups.edit');
    Route::patch('/access-groups/{group}', [AdminController::class, 'updateAccessGroup'])->name('admin.access_groups.update');
    Route::delete('/access-groups/{group}', [AdminController::class, 'deleteAccessGroup'])->name('admin.access_groups.delete');

    // Statistiche
    Route::get('/statistics', [AdminController::class, 'statistics'])->name('admin.statistics');
    Route::get('/statistics/export/csv', [AdminController::class, 'exportStatisticsCSV'])->name('admin.statistics.export.csv');
    Route::get('/statistics/export/json', [AdminController::class, 'exportStatisticsJSON'])->name('admin.statistics.export.json');
    Route::get('/statistics/details', [AdminController::class, 'statisticsDetails'])->name('admin.statistics.details');

    // Database
    Route::get('/database', [AdminController::class, 'database'])->name('admin.database');
    Route::post('/database/query', [AdminController::class, 'executeQuery'])->name('admin.database.query');

    // Test Mail
    Route::get('/test-mail', [AdminController::class, 'testMail'])->name('admin.test-mail');
});

// ========== JOB ROUTES ==========
Route::get("/job/ProcessEmailQueue", [JobController::class, 'processEmailQueue'])
    ->name("job.processEmailQueue");

// ========== TELEGRAM BOT ROUTES ==========
Route::post('/telegram/webhook', [TelegramBotController::class, 'webhook']);
Route::get('/api/notify-update', [TelegramBotController::class, 'notifyUpdate'])->name('api.notify-update');

Route::fallback(function () {
    return view('errors.404');
});