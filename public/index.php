<?php

// ===== DEBUG MODE - RIMUOVERE IN PRODUZIONE =====
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "=== DEBUG LARAVEL BOOTSTRAP ===\n\n";

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
$autoloadPath = __DIR__.'/../vendor/autoload.php';
require $autoloadPath;
echo "STEP 1: Autoload OK\n";

// Bootstrap Laravel
$bootstrapPath = __DIR__.'/../bootstrap/app.php';

try {
    /** @var Application $app */
    $app = require_once $bootstrapPath;
    echo "STEP 2: Bootstrap OK\n";
    echo "App class: " . get_class($app) . "\n";
} catch (Throwable $e) {
    die("ERRORE nel bootstrap: " . $e->getMessage() . "\n\n" . $e->getTraceAsString());
}

// Verifica i service providers registrati
echo "\nSTEP 3: Controllo Service Providers...\n";

try {
    // Prova a fare il boot manualmente
    $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
    echo "STEP 4: Kernel creato OK\n";
} catch (Throwable $e) {
    echo "ERRORE creazione Kernel: " . $e->getMessage() . "\n";
}

// Verifica binding 'view'
echo "\nSTEP 5: Verifica binding container...\n";
$bindings = ['view', 'view.finder', 'blade.compiler', 'config', 'router', 'events'];
foreach ($bindings as $binding) {
    try {
        if ($app->bound($binding)) {
            echo "✓ '$binding' è registrato\n";
        } else {
            echo "✗ '$binding' NON è registrato!\n";
        }
    } catch (Throwable $e) {
        echo "✗ '$binding' errore: " . $e->getMessage() . "\n";
    }
}

// Verifica providers caricati
echo "\nSTEP 6: Service Providers caricati:\n";
try {
    $loadedProviders = $app->getLoadedProviders();
    foreach ($loadedProviders as $provider => $loaded) {
        echo "- $provider\n";
    }
    if (empty($loadedProviders)) {
        echo "(nessun provider caricato!)\n";
    }
} catch (Throwable $e) {
    echo "Errore lettura providers: " . $e->getMessage() . "\n";
}

echo "\n=== FINE DEBUG ===\n";
echo "</pre>";

// Commenta queste righe per fermarti al debug
// try {
//     $app->handleRequest(Request::capture());
// } catch (Throwable $e) {
//     die("ERRORE nella gestione richiesta: " . $e->getMessage() . "\n\nStack trace:\n" . $e->getTraceAsString());
// }
