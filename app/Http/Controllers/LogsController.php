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
        if (!Auth::check() || !Auth::user()->isAdmin()) {
            return view("errors.403");
        }

        $currentPath = $request->get('path', '');
        $sessionIndex = $request->get('session'); // Per laravel.log sessioni virtuali
        $logsBasePath = storage_path('logs');
        $fullPath = $this->sanitizePath($logsBasePath, $currentPath);

        // Verifica che il path sia valido e dentro la cartella logs
        if (!$fullPath || !file_exists($fullPath)) {
            return redirect()->route('admin.logs')->with('error', 'Percorso non valido');
        }

        $breadcrumbs = $this->generateBreadcrumbs($currentPath);

        if (is_dir($fullPath)) {
            $contents = array_reverse($this->getDirectoryContents($fullPath, $currentPath));
            return view('admin.logs', [
                'contents' => $contents,
                'currentPath' => $currentPath,
                'breadcrumbs' => $breadcrumbs,
                'isFile' => false,
                'fileContent' => null,
            ]);
        } else {
            $fileName = basename($fullPath);


            $fileContent = $this->getFileContent($fullPath);
            return view('admin.logs', [
                'contents' => [],
                'currentPath' => $currentPath,
                'breadcrumbs' => $breadcrumbs,
                'isFile' => true,
                'fileContent' => $fileContent,
                'fileName' => $fileName,
            ]);
        }
    }

    /**
     * Visualizzazione speciale per laravel.log con sessioni virtuali
     */
    private function showLaravelLog(string $fullPath, string $currentPath, array $breadcrumbs, ?string $sessionIndex)
    {
        $sessions = $this->parseLogSessions($fullPath);

        // Se è richiesta una sessione specifica
        if ($sessionIndex !== null && isset($sessions[(int) $sessionIndex])) {
            $session = $sessions[(int) $sessionIndex];
            return view('admin.logs', [
                'contents' => [],
                'currentPath' => $currentPath,
                'breadcrumbs' => $breadcrumbs,
                'isFile' => true,
                'fileContent' => $session['content'],
                'fileName' => "laravel.log - Sessione #{$sessionIndex}",
                'sessionInfo' => $session,
            ]);
        }

        // Mostra lista sessioni (come directory virtuale)
        return view('admin.logs-sessions', [
            'sessions' => $sessions,
            'currentPath' => $currentPath,
            'breadcrumbs' => $breadcrumbs,
            'fileName' => 'laravel.log',
        ]);
    }

    /**
     * Parsa il file laravel.log e divide in sessioni
     */
    private function parseLogSessions(string $path): array
    {
        $maxSize = 5 * 1024 * 1024; // 5MB max
        $size = filesize($path);

        if ($size > $maxSize) {
            // Leggi solo gli ultimi 5MB
            $handle = fopen($path, 'r');
            fseek($handle, -$maxSize, SEEK_END);
            $content = fread($handle, $maxSize);
            fclose($handle);
        } else {
            $content = File::get($path);
        }

        $sessions = [];
        $currentSession = null;
        $currentContent = '';
        $sessionCount = 0;
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            // Rileva marker di INIZIO
            if (preg_match('/EXECUTION_START \[([a-f0-9-]+)\] (.+)$/', $line, $matches)) {
                // Salva sessione precedente se esiste
                if ($currentSession !== null) {
                    $currentSession['content'] = trim($currentContent);
                    $currentSession['status'] = 'crashed'; // Non ha avuto END
                    $sessions[] = $currentSession;
                    $sessionCount++;
                }

                // Estrai timestamp dalla riga di log Laravel
                $timestamp = $this->extractTimestamp($line);

                $currentSession = [
                    'id' => $matches[1],
                    'request' => $matches[2],
                    'start_time' => $timestamp,
                    'end_time' => null,
                    'status' => 'running',
                    'index' => $sessionCount,
                    'has_errors' => false,
                ];
                $currentContent = $line . "\n";
            }
            // Rileva marker di FINE normale
            elseif (preg_match('/EXECUTION_END \[([a-f0-9-]+)\]/', $line, $matches)) {
                if ($currentSession !== null && $currentSession['id'] === $matches[1]) {
                    $currentContent .= $line . "\n";
                    $currentSession['content'] = trim($currentContent);
                    $currentSession['end_time'] = $this->extractTimestamp($line);
                    $currentSession['status'] = 'completed';
                    $sessions[] = $currentSession;
                    $sessionCount++;
                    $currentSession = null;
                    $currentContent = '';
                }
            }
            // Rileva marker di CRASH
            elseif (preg_match('/EXECUTION_CRASH \[([a-f0-9-]+)\]/', $line, $matches)) {
                if ($currentSession !== null && $currentSession['id'] === $matches[1]) {
                    $currentContent .= $line . "\n";
                    $currentSession['content'] = trim($currentContent);
                    $currentSession['end_time'] = $this->extractTimestamp($line);
                    $currentSession['status'] = 'crashed';
                    $sessions[] = $currentSession;
                    $sessionCount++;
                    $currentSession = null;
                    $currentContent = '';
                }
            }
            // Rileva errori/warning nel contenuto
            elseif ($currentSession !== null) {
                $currentContent .= $line . "\n";
                if (preg_match('/\.(ERROR|CRITICAL|ALERT|EMERGENCY):/i', $line)) {
                    $currentSession['has_errors'] = true;
                }
            }
            // Log orfani (prima del primo marker o senza marker)
            else {
                // Accumula in una sessione "legacy"
                if (empty($sessions) || end($sessions)['id'] !== 'legacy') {
                    $sessions[] = [
                        'id' => 'legacy',
                        'request' => 'Log precedenti (senza marker)',
                        'start_time' => $this->extractTimestamp($line) ?: 'N/A',
                        'end_time' => null,
                        'status' => 'legacy',
                        'index' => $sessionCount,
                        'has_errors' => false,
                        'content' => '',
                    ];
                    $sessionCount++;
                }
                $lastIndex = count($sessions) - 1;
                $sessions[$lastIndex]['content'] .= $line . "\n";
                if (preg_match('/\.(ERROR|CRITICAL|ALERT|EMERGENCY):/i', $line)) {
                    $sessions[$lastIndex]['has_errors'] = true;
                }
            }
        }

        // Sessione ancora aperta alla fine del file
        if ($currentSession !== null) {
            $currentSession['content'] = trim($currentContent);
            $currentSession['status'] = 'incomplete';
            $sessions[] = $currentSession;
        }

        // Inverti per mostrare le più recenti prima
        return array_reverse($sessions, false);
    }

    /**
     * Estrae timestamp da una riga di log Laravel
     */
    private function extractTimestamp(string $line): ?string
    {
        if (preg_match('/\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:\d{2})?)\]/', $line, $matches)) {
            return $matches[1];
        }
        return null;
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

