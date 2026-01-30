<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class CustomLogger
{
    /**
     * Mappa delle files aperti per nota nella singola richiesta
     * key = note identifier
     */
    private static array $files = [];

    private static function ensureDirectory(string $dir): void
    {
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
    }

    private static function sanitizeFilename(string $name): string
    {
        // Rimpiazza caratteri proibiti da filename con underscore
        $s = preg_replace('/[\\\\\/\:\*\?"<>\|]+/', '_', $name);
        // Rimpiazza spazi e sequenze multiple di underscore con singolo underscore
        $s = preg_replace('/[\s]+/', '_', $s);
        $s = preg_replace('/_+/', '_', $s);
        // Rimuovi caratteri non desiderati
        $s = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $s);
        return trim($s, '_');
    }

    /**
     * Scrive un log relativo a una nota.
     * Il file viene creato in `storage/logs/notes` con nome: YYYY-MM-DD_HH-MM-SS_<nomeNota>.log
     */
    public static function note(string $noteIdentifier, string $message, string $level = 'info'): void
    {
        $dir = storage_path('logs' . DIRECTORY_SEPARATOR . 'notes');
        self::ensureDirectory($dir);

        $key = $noteIdentifier;
        if (!isset(self::$files[$key])) {
            $safeName = self::sanitizeFilename($noteIdentifier ?: 'note');
            $filename = date('Y-m-d_H-i-s') . '_' . ($safeName ?: 'note') . '.log';
            self::$files[$key] = $dir . DIRECTORY_SEPARATOR . $filename;
        }

        $filePath = self::$files[$key];
        $time = date('Y-m-d H:i:s');
        $line = "[{$time}] {" . strtoupper($level) . "}: {$message}" . PHP_EOL;

        // Appendi in modo sicuro
        @file_put_contents($filePath, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Utility generica per scrivere su path dati (opzionale)
     */
    public static function writeToPath(string $relativePath, string $message, string $level = 'info'): void
    {
        $dir = storage_path('logs' . DIRECTORY_SEPARATOR . dirname($relativePath));
        self::ensureDirectory($dir);
        $filePath = storage_path('logs' . DIRECTORY_SEPARATOR . $relativePath);
        $time = date('Y-m-d H:i:s');
        $line = "[{$time}] {" . strtoupper($level) . "}: {$message}" . PHP_EOL;
        @file_put_contents($filePath, $line, FILE_APPEND | LOCK_EX);
    }

    private static string $graphFile = '';

    /**
     * Scrive un log relativo alla generazione del grafo.
     * Il file viene creato in `storage/logs/graph` con nome basato sul timestamp di inizio richiesta.
     */
    public static function graph(string $message, string $level = 'info'): void
    {
        $dir = storage_path('logs' . DIRECTORY_SEPARATOR . 'graph');
        self::ensureDirectory($dir);

        if (empty(self::$graphFile)) {
            self::$graphFile = $dir . DIRECTORY_SEPARATOR . date('Y-m-d_H-i-s') . '.log';
        }

        $time = date('Y-m-d H:i:s');
        $line = "[{$time}] {" . strtoupper($level) . "}: {$message}" . PHP_EOL;

        @file_put_contents(self::$graphFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Scrive un log relativo a una sessione DM Screen o Player View.
     * $sessionIdentifier format: {share_code}-{master/player}
     */
    public static function screen(string $sessionIdentifier, string $message, string $level = 'info'): void
    {
        $dir = storage_path('logs' . DIRECTORY_SEPARATOR . 'screen');
        self::ensureDirectory($dir);

        $key = 'screen_' . $sessionIdentifier;
        if (!isset(self::$files[$key])) {
            $safeName = self::sanitizeFilename($sessionIdentifier ?: 'session');
            $filename = date('Y-m-d_H-i-s') . '_' . ($safeName ?: 'session') . '.log';
            self::$files[$key] = $dir . DIRECTORY_SEPARATOR . $filename;
        }

        $filePath = self::$files[$key];
        $time = date('Y-m-d H:i:s');
        $line = "[{$time}] {" . strtoupper($level) . "}: {$message}" . PHP_EOL;

        @file_put_contents($filePath, $line, FILE_APPEND | LOCK_EX);
    }
    /**
     * Scrive un log relativo al Bot Telegram.
     */
    public static function telegram(string $message, string $level = 'info'): void
    {
        $dir = storage_path('logs' . DIRECTORY_SEPARATOR . 'telegram');
        self::ensureDirectory($dir);

        $key = 'telegram_logs';
        if (!isset(self::$files[$key])) {
            $filename = date('Y-m-d') . '_telegram.log';
            self::$files[$key] = $dir . DIRECTORY_SEPARATOR . $filename;
        }

        $filePath = self::$files[$key];
        $time = date('Y-m-d H:i:s');
        $line = "[{$time}] {" . strtoupper($level) . "}: {$message}" . PHP_EOL;

        @file_put_contents($filePath, $line, FILE_APPEND | LOCK_EX);
    }
}
