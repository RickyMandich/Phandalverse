<?php

// ===== DEBUG MODE - RIMUOVERE IN PRODUZIONE =====
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!-- DEBUG STEP 1: PHP funziona -->\n";

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

echo "<!-- DEBUG STEP 2: Costanti definite -->\n";

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

echo "<!-- DEBUG STEP 3: Controllo maintenance passato -->\n";

// Verifica che il file autoload esista
$autoloadPath = __DIR__.'/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("ERRORE: File autoload.php non trovato in: " . realpath(__DIR__.'/../') . "/vendor/autoload.php");
}

echo "<!-- DEBUG STEP 4: File autoload.php trovato -->\n";

// Register the Composer autoloader...
try {
    require $autoloadPath;
    echo "<!-- DEBUG STEP 5: Autoload caricato con successo -->\n";
} catch (Throwable $e) {
    die("ERRORE nel caricamento autoload: " . $e->getMessage());
}

// Verifica che bootstrap/app.php esista
$bootstrapPath = __DIR__.'/../bootstrap/app.php';
if (!file_exists($bootstrapPath)) {
    die("ERRORE: File bootstrap/app.php non trovato");
}

echo "<!-- DEBUG STEP 6: File bootstrap/app.php trovato -->\n";

// Bootstrap Laravel and handle the request...
try {
    /** @var Application $app */
    $app = require_once $bootstrapPath;
    echo "<!-- DEBUG STEP 7: Bootstrap completato -->\n";
} catch (Throwable $e) {
    die("ERRORE nel bootstrap: " . $e->getMessage() . "\n\nStack trace:\n" . $e->getTraceAsString());
}

try {
    echo "<!-- DEBUG STEP 8: Avvio gestione richiesta -->\n";
    $app->handleRequest(Request::capture());
} catch (Throwable $e) {
    die("ERRORE nella gestione richiesta: " . $e->getMessage() . "\n\nStack trace:\n" . $e->getTraceAsString());
}
