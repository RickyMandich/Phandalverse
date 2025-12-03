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
     * Log operazioni del processore
     */
    public static function logProcessor(string $message, string $level = 'INFO', ?string $logFile = null): void
    {
        if (!$logFile) {
            $logFile = self::getOrCreateLogFile('processor');
        }
        self::writeToFile($logFile, $message, $level);
    }

    /**
     * Crea file di log (alias pubblico per compatibilità)
     */
    public static function createLogFile(string $type): string
    {
        return self::getOrCreateLogFile($type);
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

