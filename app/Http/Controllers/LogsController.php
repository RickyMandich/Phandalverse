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

