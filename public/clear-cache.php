<?php
/**
 * Script per pulire la cache di Laravel su hosting senza accesso CLI
 * ELIMINARE QUESTO FILE DOPO L'USO!
 */

echo "<h1>Pulizia Cache Laravel</h1>";
echo "<pre>";

$basePath = __DIR__ . '/..';

// File da eliminare in bootstrap/cache
$cacheFiles = [
    $basePath . '/bootstrap/cache/packages.php',
    $basePath . '/bootstrap/cache/services.php',
    $basePath . '/bootstrap/cache/config.php',
    $basePath . '/bootstrap/cache/routes-v7.php',
    $basePath . '/bootstrap/cache/events.php',
];

// Elimina file di cache specifici
foreach ($cacheFiles as $file) {
    if (file_exists($file)) {
        if (unlink($file)) {
            echo "✓ Eliminato: " . basename($file) . "\n";
        } else {
            echo "✗ Impossibile eliminare: " . basename($file) . "\n";
        }
    } else {
        echo "- Non trovato: " . basename($file) . "\n";
    }
}

// Elimina tutti i file .tmp nella cartella bootstrap/cache
$tmpFiles = glob($basePath . '/bootstrap/cache/*.tmp');
foreach ($tmpFiles as $tmpFile) {
    if (unlink($tmpFile)) {
        echo "✓ Eliminato tmp: " . basename($tmpFile) . "\n";
    }
}

// Pulisci la cartella storage/framework/cache
$storageCacheDir = $basePath . '/storage/framework/cache/data';
if (is_dir($storageCacheDir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($storageCacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    $count = 0;
    foreach ($files as $fileinfo) {
        if ($fileinfo->isFile() && $fileinfo->getFilename() !== '.gitignore') {
            unlink($fileinfo->getRealPath());
            $count++;
        }
    }
    echo "✓ Eliminati $count file dalla cache storage\n";
}

// Pulisci le views compilate
$viewsDir = $basePath . '/storage/framework/views';
if (is_dir($viewsDir)) {
    $files = glob($viewsDir . '/*.php');
    $count = 0;
    foreach ($files as $file) {
        unlink($file);
        $count++;
    }
    echo "✓ Eliminate $count views compilate\n";
}

echo "\n<strong>Cache pulita!</strong>\n";
echo "Ora ricarica la home page del sito.\n";
echo "\n⚠️  RICORDA: Elimina questo file (clear-cache.php) dopo l'uso!\n";
echo "</pre>";

