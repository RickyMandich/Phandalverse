# Guida Completa: Sistema di Logging, Debug Mode, Gestione Errori e Mail in Laravel 12

Questa guida spiega come implementare le seguenti funzionalità in una nuova web app Laravel 12:

1. **Sistema di Visione Log** - Navigazione file di log dalla web app
2. **Debug Mode per Tipo Utente** - Debug completo per admin, errori generici per utenti
3. **Gestione Errori nel DB** - Persistenza e tracking degli errori di sistema
4. **Invio Mail agli Admin** - Notifiche automatiche con sistema di coda

---

## 📚 Indice

- [1. Sistema di Visione Log](#1-sistema-di-visione-log)
- [2. Debug Mode per Tipo Utente](#2-debug-mode-per-tipo-utente)
- [3. Gestione Errori nel DB](#3-gestione-errori-nel-db)
- [4. Invio Mail agli Admin su Errori](#4-invio-mail-agli-admin-su-errori)
- [5. Dipendenze Composer Richieste](#5-dipendenze-composer-richieste)
- [6. Riepilogo File da Creare](#6-riepilogo-file-da-creare)
- [7. Configurazione .env](#7-configurazione-env)

---

## 1. Sistema di Visione Log

### Descrizione
Il sistema permette agli admin di navigare la cartella `storage/logs` direttamente dalla web app, visualizzando file e directory con breadcrumb navigation e protezione contro directory traversal.

### 1.1 Controller: `app/Http/Controllers/LogsController.php`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;

class LogsController extends Controller
{
    /**
     * Display logs directory or file content
     */
    public function index(Request $request)
    {
        if (!Auth::admin()) {
            return view("errors.403");
        }

        $currentPath = $request->get('path', '');
        $logsBasePath = storage_path('logs');
        $fullPath = $this->sanitizePath($logsBasePath, $currentPath);
        
        // Verifica che il path sia valido e dentro la cartella logs
        if (!$fullPath || !file_exists($fullPath)) {
            return redirect()->route('admin.logs')->with('error', 'Percorso non valido');
        }

        $breadcrumbs = $this->generateBreadcrumbs($currentPath);
        
        if (is_dir($fullPath)) {
            $contents = $this->getDirectoryContents($fullPath, $currentPath);
            return view('admin.logs', [
                'contents' => $contents,
                'currentPath' => $currentPath,
                'breadcrumbs' => $breadcrumbs,
                'isFile' => false,
                'fileContent' => null,
            ]);
        } else {
            $fileContent = $this->getFileContent($fullPath);
            return view('admin.logs', [
                'contents' => [],
                'currentPath' => $currentPath,
                'breadcrumbs' => $breadcrumbs,
                'isFile' => true,
                'fileContent' => $fileContent,
                'fileName' => basename($fullPath),
            ]);
        }
    }

    /**
     * Sanitize path to prevent directory traversal attacks
     */
    private function sanitizePath(string $basePath, string $relativePath): ?string
    {
        if (empty($relativePath)) {
            return $basePath;
        }
        
        // Rimuovi caratteri pericolosi
        $relativePath = str_replace(['..', "\0"], '', $relativePath);
        $fullPath = $basePath . DIRECTORY_SEPARATOR . $relativePath;
        $realPath = realpath($fullPath);
        
        // Verifica che il path reale sia dentro la cartella logs
        if ($realPath === false || strpos($realPath, realpath($basePath)) !== 0) {
            return null;
        }
        
        return $realPath;
    }

    /**
     * Generate breadcrumb navigation
     */
    private function generateBreadcrumbs(string $currentPath): array
    {
        $breadcrumbs = [['name' => 'logs', 'path' => '']];
        
        if (empty($currentPath)) {
            return $breadcrumbs;
        }
        
        $parts = explode(DIRECTORY_SEPARATOR, $currentPath);
        $accumulatedPath = '';
        
        foreach ($parts as $part) {
            if (!empty($part)) {
                $accumulatedPath .= ($accumulatedPath ? DIRECTORY_SEPARATOR : '') . $part;
                $breadcrumbs[] = ['name' => $part, 'path' => $accumulatedPath];
            }
        }
        
        return $breadcrumbs;
    }

    /**
     * Get directory contents (files and subdirectories)
     */
    private function getDirectoryContents(string $path, string $currentPath): array
    {
        $contents = [];
        $items = File::files($path);
        $directories = File::directories($path);
        
        // Prima le directory
        foreach ($directories as $dir) {
            $name = basename($dir);
            $relativePath = $currentPath ? $currentPath . DIRECTORY_SEPARATOR . $name : $name;
            $contents[] = [
                'name' => $name,
                'path' => $relativePath,
                'type' => 'directory',
                'size' => null,
                'modified' => File::lastModified($dir),
            ];
        }
        
        // Poi i file
        foreach ($items as $file) {
            $name = $file->getFilename();
            $relativePath = $currentPath ? $currentPath . DIRECTORY_SEPARATOR . $name : $name;
            $contents[] = [
                'name' => $name,
                'path' => $relativePath,
                'type' => 'file',
                'size' => $file->getSize(),
                'modified' => $file->getMTime(),
            ];
        }
        
        return $contents;
    }

    /**
     * Get file content with size limit
     */
    private function getFileContent(string $path): string
    {
        $maxSize = 1024 * 1024; // 1MB limit
        $size = filesize($path);

        if ($size > $maxSize) {
            // Leggi solo gli ultimi 1MB
            $handle = fopen($path, 'r');
            fseek($handle, -$maxSize, SEEK_END);
            $content = fread($handle, $maxSize);
            fclose($handle);
            return "... [File troncato, mostrati ultimi " . number_format($maxSize / 1024) . "KB] ...\n\n" . $content;
        }

        return File::get($path);
    }
}
```

### 1.2 Route da aggiungere in `routes/web.php`

```php
use App\Http\Controllers\LogsController;

Route::get('/admin/logs', [LogsController::class, 'index'])
    ->name("admin.logs")
    ->middleware('auth');
```

### 1.3 View: `resources/views/admin/logs.blade.php`

```blade
@extends('layouts.app')

@section('title', 'Log di Sistema')

@section('content')
<div class="container-fluid">
    <h3><i class="fas fa-file-alt"></i> Log di Sistema</h3>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            @foreach($breadcrumbs as $crumb)
                @if($loop->last)
                    <li class="breadcrumb-item active">{{ $crumb['name'] }}</li>
                @else
                    <li class="breadcrumb-item">
                        <a href="{{ route('admin.logs', ['path' => $crumb['path']]) }}">{{ $crumb['name'] }}</a>
                    </li>
                @endif
            @endforeach
        </ol>
    </nav>

    @if($isFile)
        <!-- Visualizzazione File -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-file-code"></i> {{ $fileName }}
            </div>
            <div class="card-body">
                <pre class="bg-dark text-light p-3" style="max-height: 600px; overflow: auto;">{{ $fileContent }}</pre>
            </div>
        </div>
    @else
        <!-- Lista Directory -->
        <div class="card">
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Dimensione</th>
                            <th>Modificato</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contents as $item)
                        <tr>
                            <td>
                                <a href="{{ route('admin.logs', ['path' => $item['path']]) }}">
                                    @if($item['type'] === 'directory')
                                        <i class="fas fa-folder text-warning"></i>
                                    @else
                                        <i class="fas fa-file-alt text-secondary"></i>
                                    @endif
                                    {{ $item['name'] }}
                                </a>
                            </td>
                            <td>{{ $item['size'] ? number_format($item['size'] / 1024, 2) . ' KB' : '-' }}</td>
                            <td>{{ date('d/m/Y H:i', $item['modified']) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center">Nessun file trovato</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
```

---

## 2. Debug Mode per Tipo Utente

### Descrizione
Il sistema modifica dinamicamente `APP_DEBUG` in base al tipo di utente:
- **Admin**: vedono il debug completo con stack trace, file, linea, headers
- **Utenti normali**: vedono pagine errore generiche senza dettagli tecnici

### 2.1 Prerequisito: Metodo isAdmin nel Model User

Aggiungi questi metodi in `app/Models/User.php`:

```php
/**
 * Check if user is admin
 */
public function isAdmin(): bool
{
    return $this->admin == 1;
}

/**
 * Get all admin users for notifications
 */
public static function getAdmins()
{
    return static::where('admin', 1)->get();
}
```

### 2.2 Helper Auth::admin() (Opzionale)

Per usare `Auth::admin()` globalmente, puoi creare un Service Provider.

**Crea `app/Providers/AuthMacroServiceProvider.php`:**

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthMacroServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Auth::macro('admin', function () {
            if (Auth::check()) {
                return Auth::user()->admin == 1;
            }
            return false;
        });
    }
}
```

**Registra il provider in `bootstrap/providers.php`:**

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthMacroServiceProvider::class,
];
```

### 2.3 Configurazione Exception Handling in `bootstrap/app.php`

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Auth;
use App\Models\SystemError;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Configurazione middleware...
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // ========== REPORTABLE: Salva errore e invia notifiche ==========
        $exceptions->reportable(function (Throwable $e) {
            $systemError = null;
            $requestUrl = null;
            $requestMethod = null;
            $userAgent = null;

            try {
                $requestUrl = request()->fullUrl();
                $requestMethod = request()->method();
                $userAgent = request()->userAgent();
            } catch (\Exception $reqEx) {
                // Ignora errori nel recupero della request
            }

            // 1. SALVA ERRORE NEL DATABASE
            try {
                $systemError = SystemError::create([
                    'exception_class' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'request_url' => $requestUrl,
                    'request_method' => $requestMethod,
                    'user_agent' => $userAgent,
                    'user_id' => Auth::id(),
                    'status' => 'new',
                ]);
            } catch (\Exception $dbException) {
                \Log::error("Impossibile salvare errore nel database: " . $dbException->getMessage());
            }

            // 2. INVIA EMAIL A TUTTI GLI ADMIN
            try {
                \App\Services\EmailQueueService::queueToAdmins(
                    new \App\Mail\ErrorNotificationEmail($e, $requestUrl, $requestMethod, $userAgent, $systemError),
                    'Notifica errore sistema'
                );
            } catch (\Exception $emailException) {
                \Log::error("Impossibile inviare email errore: " . $emailException->getMessage());
            }
        });

        // ========== RENDERABLE: Debug differenziato per tipo utente ==========
        $exceptions->renderable(function (Throwable $e, $request) {
            // Se l'utente è admin, mostra debug completo
            if (Auth::admin()) {
                config(['app.debug' => true]);

                return response()->view('errors.admin-debug', [
                    'exception' => $e,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'request' => $request,
                ], 500);
            }

            // Per utenti normali, nascondi i dettagli
            config(['app.debug' => false]);
            return null; // Usa il comportamento default di Laravel
        });
    });
```

### 2.4 View Debug Admin: `resources/views/errors/admin-debug.blade.php`

```blade
@extends('layouts.app')

@section('title', 'Errore - Debug Admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="alert alert-danger mb-3">
                <h4><i class="fas fa-exclamation-triangle"></i> Errore Admin Debug</h4>
                <p class="mb-0">Stai visualizzando questa pagina perché sei un amministratore. Gli utenti normali vedono una pagina di errore semplificata.</p>
            </div>

            <!-- Informazioni principali dell'errore -->
            <div class="card mb-3">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-bug"></i> {{ get_class($exception) }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Messaggio:</strong>
                            <p class="text-danger">{{ $message }}</p>
                        </div>
                        <div class="col-md-6">
                            <strong>File:</strong>
                            <p><code class="text-muted">{{ $file }}:{{ $line }}</code></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stack Trace -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-list"></i> Stack Trace</h6>
                </div>
                <div class="card-body">
                    <div class="overflow-auto" style="max-height: 400px;">
                        <pre class="bg-dark text-light p-3 small">{{ $trace }}</pre>
                    </div>
                </div>
            </div>

            <!-- Informazioni Request -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-globe"></i> Informazioni Request</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <strong>URL:</strong>
                            <p><code>{{ $request->fullUrl() }}</code></p>
                        </div>
                        <div class="col-md-4">
                            <strong>Metodo:</strong>
                            <p><span class="badge bg-primary">{{ $request->method() }}</span></p>
                        </div>
                        <div class="col-md-4">
                            <strong>IP:</strong>
                            <p><code>{{ $request->ip() }}</code></p>
                        </div>
                    </div>

                    @if($request->all())
                    <div class="mt-3">
                        <strong>Parametri:</strong>
                        <pre class="p-2 mt-2">{{ json_encode($request->all(), JSON_PRETTY_PRINT) }}</pre>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Headers -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-tags"></i> Headers</h6>
                </div>
                <div class="card-body">
                    <pre class="p-2 small" style="max-height: 300px; overflow: auto;">{{ json_encode($request->headers->all(), JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
```

---

## 3. Gestione Errori nel DB

### Descrizione
Ogni errore viene salvato in una tabella `system_errors` per tracking, gestione e risoluzione da parte degli admin.

### 3.1 Migration: `database/migrations/xxxx_create_system_errors_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_errors', function (Blueprint $table) {
            $table->id();
            $table->string('exception_class');
            $table->text('message');
            $table->text('file');
            $table->integer('line');
            $table->longText('trace');
            $table->string('request_url')->nullable();
            $table->string('request_method')->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('status', ['new', 'in_progress', 'resolved', 'ignored'])->default('new');
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');

            // Indexes per performance
            $table->index(['status', 'created_at']);
            $table->index('exception_class');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_errors');
    }
};
```

### 3.2 Model: `app/Models/SystemError.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemError extends Model
{
    use HasFactory;

    protected $fillable = [
        'exception_class',
        'message',
        'file',
        'line',
        'trace',
        'request_url',
        'request_method',
        'user_agent',
        'user_id',
        'status',
        'admin_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    /**
     * L'utente che ha causato l'errore (se loggato)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * L'admin che ha risolto l'errore
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope per errori non risolti
     */
    public function scopeUnresolved($query)
    {
        return $query->whereIn('status', ['new', 'in_progress']);
    }

    /**
     * Scope per errori risolti
     */
    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    /**
     * Segna come in lavorazione
     */
    public function markAsInProgress(): void
    {
        $this->update(['status' => 'in_progress']);
    }

    /**
     * Segna come risolto
     */
    public function markAsResolved(User $admin, ?string $notes = null): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
            'admin_notes' => $notes ?? $this->admin_notes,
        ]);
    }

    /**
     * Segna come ignorato
     */
    public function markAsIgnored(User $admin, ?string $notes = null): void
    {
        $this->update([
            'status' => 'ignored',
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
            'admin_notes' => $notes ?? $this->admin_notes,
        ]);
    }

    /**
     * Ottieni il nome breve della classe eccezione
     */
    public function getShortClassNameAttribute(): string
    {
        return class_basename($this->exception_class);
    }
}
```

---

## 4. Invio Mail agli Admin su Errori

### Descrizione
Sistema completo con:
- **Sistema "Fire and Forget"** - NON usa `php artisan queue:work` (impraticabile in produzione)
- **Processore HTTP asincrono** - Si auto-attiva quando ci sono email in coda
- **Rate limiting** (1 email/secondo) per evitare overflow del provider
- **Mailable dedicato** per notifiche errori con azioni rapide
- **Servizio di logging** per tracciare tutte le operazioni email
- **Job con retry** automatico in caso di fallimento

### ⚠️ IMPORTANTE: Sistema Fire and Forget

Questa app **NON** usa `php artisan queue:work` perché:
1. Richiede un processo sempre attivo in background
2. Necessita di Supervisor o simili in produzione
3. È complicato da gestire su hosting condivisi

Invece, usa un sistema **Fire and Forget** che:
1. Quando si accoda un'email, fa una chiamata HTTP asincrona a se stesso
2. Il processore elabora le email in coda
3. Si riavvia automaticamente se ci sono ancora email da processare
4. Non blocca la richiesta dell'utente

### 4.1 JobController con Fire and Forget: `app/Http/Controllers/JobController.php`

Questo controller è il cuore del sistema. Contiene i metodi per:
- Inviare richieste HTTP asincrone senza aspettare risposta
- Processare la coda email

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\EmailLogService;

class JobController extends Controller
{
    /**
     * Esegue una richiesta GET "fire-and-forget" senza aspettare la risposta
     * Usa socket raw per inviare la richiesta e chiudere subito la connessione
     */
    public static function fireAndForgetGet($url, $data = []) {
        $query = http_build_query($data);
        $parts = parse_url($url);

        if (!isset($parts['host']) || !isset($parts['path'])) {
            return false;
        }

        $path = $parts['path'];
        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'] . '&' . $query;
        } elseif ($query !== '') {
            $path .= '?' . $query;
        }

        // Usa fsockopen per una connessione asincrona
        $fp = fsockopen($parts['host'], $parts['port'] ?? 80, $errno, $errstr, 30);

        if (!$fp) {
            return false;
        }

        $out = "GET " . $path . " HTTP/1.1\r\n";
        $out .= "Host: " . $parts['host'] . "\r\n";
        $out .= "Connection: Close\r\n\r\n";

        fwrite($fp, $out);
        fclose($fp); // Chiude subito, senza aspettare risposta

        return true;
    }

    /**
     * Processa la coda email con rate limiting
     * Questo metodo viene chiamato via fire-and-forget
     */
    public function processEmailQueue(Request $request)
    {
        // Verifica token per sicurezza
        if ($request->input('token') !== env('JOB_TOKEN')) {
            abort(403, 'Unauthorized');
        }

        $logFile = EmailLogService::createLogFile('processor');
        $startTime = time();
        $maxExecutionTime = 240; // 4 minuti limite
        $processedCount = 0;

        try {
            // Recupera i job email dalla tabella jobs
            $pendingJobs = \DB::table('jobs')
                ->where('queue', 'emails')
                ->orderBy('available_at', 'asc')
                ->limit(50)
                ->get();

            EmailLogService::logProcessor("Job trovati: {$pendingJobs->count()}", 'INFO', $logFile);

            if ($pendingJobs->isEmpty()) {
                return;
            }

            foreach ($pendingJobs as $jobRecord) {
                // Controlla timeout
                if ((time() - $startTime) > $maxExecutionTime) {
                    // Riavvia il processore per i job rimanenti
                    $remainingJobs = \DB::table('jobs')->where('queue', 'emails')->count();
                    if ($remainingJobs > 0) {
                        self::fireAndForgetGet(route('job.processEmailQueue'), [
                            'token' => env('JOB_TOKEN')
                        ]);
                    }
                    return;
                }

                // Rate limiting: 1 secondo tra ogni email
                sleep(1);

                try {
                    $payload = json_decode($jobRecord->payload, true);
                    $jobClass = $payload['displayName'] ?? null;

                    if ($jobClass === 'App\\Jobs\\SendQueuedEmail') {
                        $jobData = unserialize($payload['data']['command']);

                        // Esegui l'invio email
                        $jobData->handle();

                        // Rimuovi dalla coda
                        \DB::table('jobs')->where('id', $jobRecord->id)->delete();
                        $processedCount++;
                    }

                } catch (\Exception $e) {
                    // Gestisci errore: sposta in failed_jobs
                    \DB::table('jobs')->where('id', $jobRecord->id)->delete();
                    \DB::table('failed_jobs')->insert([
                        'uuid' => Str::uuid(),
                        'connection' => 'database',
                        'queue' => 'emails',
                        'payload' => $jobRecord->payload,
                        'exception' => $e->getMessage(),
                        'failed_at' => now()
                    ]);
                }
            }

            // Controlla se ci sono altri job
            $remainingJobs = \DB::table('jobs')->where('queue', 'emails')->count();
            if ($remainingJobs > 0) {
                // Riavvia automaticamente
                self::fireAndForgetGet(route('job.processEmailQueue'), [
                    'token' => env('JOB_TOKEN')
                ]);
            }

        } catch (\Exception $e) {
            EmailLogService::logError('Email Queue Processor', $e);
        }
    }
}
```

### 4.2 Service: `app/Services/EmailQueueService.php`

```php
<?php

namespace App\Services;

use App\Jobs\SendQueuedEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Services\EmailLogService;

class EmailQueueService
{
    /**
     * Mette in coda un'email
     */
    public static function queue($mailable, $to, string $logContext = '', int $delay = 0)
    {
        if (is_array($to)) {
            foreach ($to as $recipient) {
                self::queueSingle($mailable, $recipient, $logContext, $delay);
            }
            return;
        }

        self::queueSingle($mailable, $to, $logContext, $delay);
    }

    /**
     * Mette in coda una singola email
     */
    protected static function queueSingle($mailable, string $to, string $logContext = '', int $delay = 0)
    {
        try {
            $job = new SendQueuedEmail($mailable, $to, $logContext);

            if ($delay > 0) {
                $job->delay(now()->addSeconds($delay));
            }

            dispatch($job);

            // CRUCIALE: Avvia il processore fire-and-forget
            self::triggerQueueProcessor();

            EmailLogService::logQueue("Email accodata per: {$to}");

        } catch (\Exception $e) {
            EmailLogService::logError('Email Queue', $e, ['to' => $to]);
        }
    }

    /**
     * Avvia il processore coda email via fire-and-forget
     * Si attiva solo se non è già in esecuzione (throttle 30 secondi)
     */
    protected static function triggerQueueProcessor()
    {
        try {
            $lastProcessorRun = \Cache::get('email_processor_last_run', 0);
            $now = time();

            // Avvia solo se non è stato eseguito negli ultimi 30 secondi
            if (($now - $lastProcessorRun) > 30) {
                \Cache::put('email_processor_last_run', $now, 60);

                \App\Http\Controllers\JobController::fireAndForgetGet(
                    route('job.processEmailQueue'),
                    ['token' => env('JOB_TOKEN')]
                );

                EmailLogService::logProcessor('Processore avviato automaticamente');
            }

        } catch (\Exception $e) {
            EmailLogService::logError('Trigger Queue Processor', $e);
        }
    }

    /**
     * Invia a tutti gli admin
     */
    public static function queueToAdmins($mailable, string $logContext = '')
    {
        try {
            $admins = \App\Models\User::getAdmins();

            if ($admins->isEmpty()) {
                Log::warning('Nessun admin trovato per la notifica');
                return;
            }

            self::queueToUsers($mailable, $admins, $logContext);

        } catch (\Exception $e) {
            EmailLogService::logError('Queue To Admins', $e);
        }
    }

    /**
     * Invia a più utenti
     */
    public static function queueToUsers($mailable, $users, string $logContext = '', int $batchDelay = 0)
    {
        foreach ($users as $user) {
            if (!empty($user->email)) {
                self::queueSingle($mailable, $user->email, $logContext, 0);
            }
        }
    }

    /**
     * Invia immediatamente (bypass coda, solo per emergenze)
     */
    public static function sendImmediate($mailable, string $to, string $logContext = ''): bool
    {
        try {
            Mail::to($to)->send($mailable);
            return true;
        } catch (\Exception $e) {
            EmailLogService::logError('Send Immediate', $e, ['to' => $to]);
            return false;
        }
    }
}
```

### 4.3 Job: `app/Jobs/SendQueuedEmail.php`

```php
<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use App\Services\EmailLogService;

class SendQueuedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $backoff = [60];
    public $timeout = 120;

    protected $mailableClass;
    protected $mailableData;
    protected $to;
    protected $logContext;

    public function __construct($mailable, string $to, string $logContext = '')
    {
        $this->queue = 'emails'; // IMPORTANTE: usa la coda 'emails'
        $this->mailableClass = get_class($mailable);
        $this->mailableData = $this->extractMailableData($mailable);
        $this->to = $to;
        $this->logContext = $logContext;
    }

    public function handle(): void
    {
        try {
            $mailable = $this->recreateMailable();
            Mail::to($this->to)->send($mailable);

            EmailLogService::logSend("Email inviata a {$this->to}");
        } catch (\Exception $e) {
            EmailLogService::logError('send_queued', $e, ['to' => $this->to]);
            throw $e;
        }
    }

    protected function extractMailableData($mailable): array
    {
        $data = [];
        $reflection = new \ReflectionClass($mailable);

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $value = $property->getValue($mailable);

            if (is_scalar($value) || is_null($value) || is_array($value)) {
                $data[$name] = $value;
            } elseif ($value instanceof \Illuminate\Database\Eloquent\Model) {
                $data[$name] = ['id' => $value->id, '_class' => get_class($value)];
            }
        }

        return $data;
    }

    protected function recreateMailable()
    {
        $class = $this->mailableClass;
        return new $class(...array_values($this->mailableData));
    }
}
```

### 4.4 Route per il Processore

**Aggiungi in `routes/web.php`:**

```php
use App\Http\Controllers\JobController;

// Route per il processore email (protetta da token)
Route::get("/job/ProcessEmailQueue", [JobController::class, 'processEmailQueue'])
    ->name("job.processEmailQueue");
```

### 4.4 Mailable: `app/Mail/ErrorNotificationEmail.php`

```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\SystemError;
use Throwable;

class ErrorNotificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $exception;
    public $exceptionClass;
    public $errorMessage;
    public $errorFile;
    public $errorLine;
    public $requestUrl;
    public $requestMethod;
    public $userAgent;
    public $timestamp;
    public $systemError;

    public function __construct(
        Throwable $exception,
        ?string $requestUrl = null,
        ?string $requestMethod = null,
        ?string $userAgent = null,
        ?SystemError $systemError = null
    ) {
        $this->exception = $exception;
        $this->exceptionClass = get_class($exception);
        $this->errorMessage = $exception->getMessage();
        $this->errorFile = $exception->getFile();
        $this->errorLine = $exception->getLine();
        $this->requestUrl = $requestUrl;
        $this->requestMethod = $requestMethod;
        $this->userAgent = $userAgent;
        $this->timestamp = now()->format('d/m/Y H:i:s');
        $this->systemError = $systemError;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[' . config('app.name') . '] Errore Sistema - ' . class_basename($this->exception),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.error-notification',
            with: [
                'exceptionClass' => $this->exceptionClass,
                'errorMessage' => $this->errorMessage,
                'errorFile' => $this->errorFile,
                'errorLine' => $this->errorLine,
                'requestUrl' => $this->requestUrl,
                'requestMethod' => $this->requestMethod,
                'userAgent' => $this->userAgent,
                'timestamp' => $this->timestamp,
                'systemError' => $this->systemError,
            ],
        );
    }
}
```

### 4.5 Template Email: `resources/views/emails/error-notification.blade.php`

```blade
@extends('emails.layout', ['hideDefaultFooter' => true])

@section('content')
    <h2 style="color: #dc3545; margin-bottom: 20px;">🚨 Errore Sistema Rilevato</h2>

    <p style="font-size: 16px; margin-bottom: 20px;">
        Si è verificato un errore sul sistema <strong>{{ config('app.name') }}</strong> che richiede la tua attenzione.
    </p>

    <div style="background-color: #495057; padding: 15px; border-radius: 5px; margin-bottom: 20px; color: #fff;">
        <h3 style="color: #e9ecef; margin-top: 0;">Dettagli Errore</h3>
        <p style="margin: 5px 0;"><strong>Tipo:</strong> {{ $exceptionClass }}</p>
        <p style="margin: 5px 0;"><strong>Messaggio:</strong> <span style="color: #dc3545;">{{ $errorMessage }}</span></p>
        <p style="margin: 5px 0;"><strong>File:</strong> <code style="background-color: #6c757d; padding: 2px 4px; border-radius: 3px;">{{ $errorFile }}</code></p>
        <p style="margin: 5px 0;"><strong>Linea:</strong> {{ $errorLine }}</p>
        <p style="margin: 5px 0;"><strong>Timestamp:</strong> {{ $timestamp }}</p>
    </div>

    @if($requestUrl || $requestMethod || $userAgent)
    <div style="background-color: #1e3a5f; padding: 15px; border-radius: 5px; margin-bottom: 20px; color: #fff;">
        <h3 style="color: #87ceeb; margin-top: 0;">Informazioni Request</h3>
        @if($requestUrl)
        <p style="margin: 5px 0;"><strong>URL:</strong> <a href="{{ $requestUrl }}" style="color: #87ceeb;">{{ $requestUrl }}</a></p>
        @endif
        @if($requestMethod)
        <p style="margin: 5px 0;"><strong>Metodo:</strong> <span style="background-color: #0d6efd; color: white; padding: 2px 6px; border-radius: 3px; font-size: 12px;">{{ $requestMethod }}</span></p>
        @endif
        @if($userAgent)
        <p style="margin: 5px 0;"><strong>User Agent:</strong> <small style="color: #adb5bd;">{{ $userAgent }}</small></p>
        @endif
    </div>
    @endif

    @if($systemError)
    <div style="background-color: #1e3a5f; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; color: #fff;">
        <h3 style="color: #87ceeb; margin-top: 0;">🔧 Azioni Rapide</h3>
        <p style="margin-bottom: 15px;">Gestisci questo errore direttamente:</p>

        <div style="margin-bottom: 15px;">
            <a href="{{ route('admin.errors.show', $systemError) }}"
               style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;">
                👁️ Visualizza Dettagli
            </a>
        </div>

        <div>
            <a href="{{ url('/admin/errors/quick-action/' . $systemError->id . '/resolved') }}"
               style="display: inline-block; padding: 8px 16px; background-color: #28a745; color: white; text-decoration: none; border-radius: 4px; margin: 0 5px;">
                ✅ Segna come Risolto
            </a>
            <a href="{{ url('/admin/errors/quick-action/' . $systemError->id . '/ignored') }}"
               style="display: inline-block; padding: 8px 16px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; margin: 0 5px;">
                ❌ Segna come Ignorato
            </a>
        </div>
    </div>
    @endif

    <hr style="border: none; border-top: 1px solid #dee2e6; margin: 20px 0;">

    <p style="font-size: 14px; color: #6c757d; margin-bottom: 0;">
        Questa è una notifica automatica del sistema di monitoraggio errori.
    </p>
@endsection
```

### 4.6 Layout Email Base: `resources/views/emails/layout.blade.php`

```blade
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #343a40; border-radius: 8px; overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #212529; padding: 20px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px;">{{ config('app.name') }}</h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px; color: #e9ecef;">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- Footer -->
                    @unless(isset($hideDefaultFooter) && $hideDefaultFooter)
                    <tr>
                        <td style="background-color: #212529; padding: 15px; text-align: center;">
                            <p style="color: #6c757d; margin: 0; font-size: 12px;">
                                © {{ date('Y') }} {{ config('app.name') }}. Tutti i diritti riservati.
                            </p>
                        </td>
                    </tr>
                    @endunless
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
```

### 4.7 Service Logging Email: `app/Services/EmailLogService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class EmailLogService
{
    /**
     * Log operazioni di coda
     */
    public static function logQueue(string $message, string $level = 'INFO', ?string $logFile = null): void
    {
        if (!$logFile) {
            $logFile = self::getOrCreateLogFile('queue');
        }
        self::writeToFile($logFile, $message, $level);
    }

    /**
     * Log operazioni di invio
     */
    public static function logSend(string $message, string $level = 'INFO', ?string $logFile = null): void
    {
        if (!$logFile) {
            $logFile = self::getOrCreateLogFile('send');
        }
        self::writeToFile($logFile, $message, $level);
    }

    /**
     * Log errori email
     */
    public static function logError(string $operation, \Exception $exception, array $context = []): void
    {
        $errorLogFile = self::getOrCreateLogFile('errors');

        self::writeToFile($errorLogFile, "=== ERRORE EMAIL - {$operation} ===", 'ERROR');
        self::writeToFile($errorLogFile, "Messaggio: " . $exception->getMessage(), 'ERROR');
        self::writeToFile($errorLogFile, "File: " . $exception->getFile(), 'ERROR');
        self::writeToFile($errorLogFile, "Linea: " . $exception->getLine(), 'ERROR');

        if (!empty($context)) {
            self::writeToFile($errorLogFile, "Contesto: " . json_encode($context, JSON_PRETTY_PRINT), 'ERROR');
        }
    }

    /**
     * Scrivi nel file di log
     */
    public static function writeToFile(string $logFile, string $message, string $level = 'INFO'): void
    {
        $timestamp = now()->format('H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";

        $logDir = dirname($logFile);
        if (!File::exists($logDir)) {
            File::makeDirectory($logDir, 0755, true);
        }

        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

        // Backup nel log Laravel
        Log::info("EMAIL: {$message}");
    }

    /**
     * Ottieni o crea file di log per oggi
     */
    private static function getOrCreateLogFile(string $type): string
    {
        $date = now()->format('Y_m_d');
        $logFile = storage_path("logs/mail/{$type}_{$date}.log");

        if (!File::exists($logFile)) {
            $logDir = dirname($logFile);
            if (!File::exists($logDir)) {
                File::makeDirectory($logDir, 0755, true);
            }
            self::writeToFile($logFile, "=== LOG " . strtoupper($type) . " EMAIL - " . now()->format('d/m/Y') . " ===");
        }

        return $logFile;
    }
}
```

---

## 5. Dipendenze Composer Richieste

Per Laravel 12, **non servono pacchetti aggiuntivi** per queste funzionalità. È tutto incluso nel framework:

```json
{
    "require": {
        "php": "^8.2",
        "laravel/framework": "^12.0"
    }
}
```

### Provider Email (opzionale)

Puoi usare qualsiasi provider email supportato da Laravel:

| Provider | Pacchetto |
|----------|-----------|
| SMTP | Incluso in Laravel |
| Mailgun | `mailgun/mailgun-php` |
| Postmark | `wildbit/postmark-php` |
| Resend | `resend/resend-laravel` |
| Amazon SES | `aws/aws-sdk-php` |

---

## 6. Riepilogo File da Creare

### Struttura Completa

```
app/
├── Http/
│   └── Controllers/
│       ├── JobController.php           # ⭐ CRUCIALE: Fire-and-forget + processore email
│       └── LogsController.php          # Visualizzazione log
├── Jobs/
│   └── SendQueuedEmail.php             # Job invio email (coda 'emails')
├── Mail/
│   └── ErrorNotificationEmail.php      # Mailable errori
├── Models/
│   ├── SystemError.php                 # Model errori
│   └── User.php                        # (aggiungere isAdmin, getAdmins)
├── Providers/
│   └── AuthMacroServiceProvider.php    # (opzionale) Macro Auth::admin()
└── Services/
    ├── EmailQueueService.php           # ⭐ Servizio coda + trigger processore
    └── EmailLogService.php             # Servizio logging email

bootstrap/
└── app.php                             # Configurazione exception handling

database/
└── migrations/
    └── xxxx_create_system_errors_table.php

resources/
└── views/
    ├── admin/
    │   └── logs.blade.php              # View visualizzazione log
    ├── emails/
    │   ├── layout.blade.php            # Layout base email
    │   └── error-notification.blade.php # Template email errore
    └── errors/
        └── admin-debug.blade.php       # View debug per admin

routes/
└── web.php                             # Routes admin + job
```

### Routes da Aggiungere

```php
// In routes/web.php

use App\Http\Controllers\LogsController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\JobController;

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

// ⭐ CRUCIALE: Route per il processore email (protetta da JOB_TOKEN)
Route::get("/job/ProcessEmailQueue", [JobController::class, 'processEmailQueue'])
    ->name("job.processEmailQueue");
```

---

## 7. Configurazione .env

```env
# ========== APP ==========
APP_NAME="La Tua App"
APP_URL=http://localhost  # IMPORTANTE: deve essere l'URL corretto per fire-and-forget

# ========== CONFIGURAZIONE EMAIL ==========
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@tuodominio.com
MAIL_FROM_NAME="${APP_NAME}"

# ========== CODA (OBBLIGATORIO) ==========
QUEUE_CONNECTION=database

# ========== TOKEN SICUREZZA JOB (OBBLIGATORIO) ==========
# Token segreto per proteggere le route dei job
# Genera con: php artisan tinker -> Str::random(32)
JOB_TOKEN=il_tuo_token_segreto_qui

# ========== DEBUG ==========
# Questo valore viene sovrascritto dinamicamente per gli admin
APP_DEBUG=false
```

### Configurazione Coda Database

Devi creare le tabelle per la coda e i job falliti:

```bash
php artisan queue:table
php artisan queue:failed-table
php artisan migrate
```

Questo crea le tabelle:
- `jobs` - contiene i job in coda
- `failed_jobs` - contiene i job falliti

### ⚠️ NON Serve `php artisan queue:work`

**Il sistema Fire and Forget si auto-gestisce:**

1. Quando acodi un'email → `EmailQueueService::queue()`
2. Automaticamente chiama → `triggerQueueProcessor()`
3. Che fa fire-and-forget verso → `/job/ProcessEmailQueue`
4. Il processore elabora le email con rate limiting (1/secondo)
5. Se ci sono altre email, si riavvia automaticamente

**Nessun processo in background necessario!**

---

## 📝 Note Finali

1. **Sicurezza**: Il metodo `Auth::admin()` verifica sempre che l'utente sia autenticato prima di controllare i permessi.

2. **Rate Limiting**: Il sistema limita a 1 email/secondo per evitare di saturare il provider (gestito nel processore, non nel job).

3. **Logging Dedicato**: Le email hanno log separati in `storage/logs/mail/` per facilitare il debug.

4. **Fallback Graceful**: Se il database non è raggiungibile, l'errore viene comunque loggato nel file Laravel standard.

5. **Quick Actions**: Le email di errore includono link per gestire rapidamente l'errore dalla casella email.

6. **Fire and Forget**: NON serve `php artisan queue:work`. Il sistema si auto-attiva quando ci sono email da inviare.

7. **JOB_TOKEN**: Protegge le route dei job da accessi non autorizzati. Genera un token sicuro!

8. **APP_URL**: Deve essere corretto altrimenti il fire-and-forget non funziona (usa fsockopen verso l'host).

---

## 🔧 Comandi Utili

```bash
# Creare le tabelle necessarie
php artisan queue:table
php artisan queue:failed-table
php artisan migrate

# Generare un JOB_TOKEN sicuro
php artisan tinker
>>> Str::random(32)

# Testare manualmente il processore (browser o curl)
curl "http://tuodominio.com/job/ProcessEmailQueue?token=TUO_JOB_TOKEN"

# Verificare job in coda
php artisan tinker
>>> DB::table('jobs')->where('queue', 'emails')->count()

# Verificare job falliti
>>> DB::table('failed_jobs')->count()

# Testare accodamento email
php artisan tinker
>>> \App\Services\EmailQueueService::queue(new \App\Mail\ErrorNotificationEmail(new \Exception('Test')), 'test@example.com', 'Test manuale');
```

---

## 🔄 Flusso Completo

```
1. Si verifica un errore
        ↓
2. bootstrap/app.php → reportable() cattura l'eccezione
        ↓
3. SystemError::create() → salva nel DB
        ↓
4. EmailQueueService::queueToAdmins() → accoda email per tutti gli admin
        ↓
5. SendQueuedEmail viene dispatchato → finisce nella tabella 'jobs'
        ↓
6. triggerQueueProcessor() → fire-and-forget verso /job/ProcessEmailQueue
        ↓
7. JobController::processEmailQueue() → elabora i job con rate limiting
        ↓
8. Email inviate! Se ci sono altri job, si riavvia automaticamente
```

