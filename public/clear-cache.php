<?php
/**
 * Script di diagnostica Laravel per hosting senza CLI
 * ELIMINARE QUESTO FILE DOPO L'USO!
 */

echo "<h1>Diagnostica Laravel</h1>";
echo "<pre>";

$basePath = realpath(__DIR__ . '/..');

echo "=== INFORMAZIONI SISTEMA ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Base Path: $basePath\n\n";

echo "=== CONTROLLO FILE ESSENZIALI ===\n";

$essentialFiles = [
    '.env' => 'File di configurazione ambiente',
    'vendor/autoload.php' => 'Autoloader Composer',
    'bootstrap/app.php' => 'Bootstrap applicazione',
    'bootstrap/providers.php' => 'Providers dell\'app',
    'config/app.php' => 'Configurazione app',
    'config/view.php' => 'Configurazione views',
    'routes/web.php' => 'Routes web',
    'storage/framework/views' => 'Cartella views compilate',
    'storage/framework/cache' => 'Cartella cache',
    'storage/framework/sessions' => 'Cartella sessioni',
    'storage/logs' => 'Cartella logs',
];

foreach ($essentialFiles as $file => $desc) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath) || is_dir($fullPath)) {
        $writable = is_writable($fullPath) ? ' [scrivibile]' : ' [NON scrivibile!]';
        echo "✓ $file $writable\n";
    } else {
        echo "✗ MANCANTE: $file - $desc\n";
    }
}

echo "\n=== CONTENUTO .ENV ===\n";
$envPath = $basePath . '/.env';
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    // Nascondi valori sensibili
    $envContent = preg_replace('/^(.*(?:KEY|PASSWORD|SECRET|TOKEN).*)=(.+)$/m', '$1=[NASCOSTO]', $envContent);
    echo $envContent;
} else {
    echo "⚠️  FILE .ENV NON TROVATO!\n";
    echo "Devi creare il file .env nella root del progetto.\n";
}

echo "\n\n=== CONTENUTO bootstrap/cache ===\n";
$cacheDir = $basePath . '/bootstrap/cache';
if (is_dir($cacheDir)) {
    $files = scandir($cacheDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "- $file\n";
        }
    }
    if (count($files) <= 2) {
        echo "(vuota)\n";
    }
} else {
    echo "✗ Cartella non trovata!\n";
}

echo "\n=== PERMESSI CARTELLE STORAGE ===\n";
$storageDirs = [
    'storage',
    'storage/app',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache',
];

foreach ($storageDirs as $dir) {
    $fullPath = $basePath . '/' . $dir;
    if (is_dir($fullPath)) {
        $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
        $writable = is_writable($fullPath) ? 'OK' : 'NON SCRIVIBILE!';
        echo "$dir: $perms - $writable\n";
    } else {
        echo "$dir: NON ESISTE!\n";
    }
}

echo "\n=== PULIZIA CACHE ===\n";
// File da eliminare in bootstrap/cache
$cacheFiles = [
    $basePath . '/bootstrap/cache/packages.php',
    $basePath . '/bootstrap/cache/services.php',
    $basePath . '/bootstrap/cache/config.php',
    $basePath . '/bootstrap/cache/routes-v7.php',
    $basePath . '/bootstrap/cache/events.php',
];

foreach ($cacheFiles as $file) {
    if (file_exists($file)) {
        if (unlink($file)) {
            echo "✓ Eliminato: " . basename($file) . "\n";
        } else {
            echo "✗ Impossibile eliminare: " . basename($file) . "\n";
        }
    }
}

// Elimina file .tmp
$tmpFiles = glob($basePath . '/bootstrap/cache/*.tmp');
foreach ($tmpFiles as $tmpFile) {
    if (unlink($tmpFile)) {
        echo "✓ Eliminato: " . basename($tmpFile) . "\n";
    }
}

echo "\n⚠️  RICORDA: Elimina questo file dopo l'uso!\n";
echo "</pre>";

