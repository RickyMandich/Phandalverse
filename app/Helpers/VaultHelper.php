<?php

namespace App\Helpers;

use App\Services\CustomLogger;
use Illuminate\Support\Facades\File;

class VaultHelper
{
    protected static $map = null;
    protected static $mapPath;

    protected static function loadMap($note, $showPrint_r = false)
    {
        if (self::$map !== null) {
            return;
        }

        self::$mapPath = base_path('/Vault/.normalize/map.json');

        if (File::exists(self::$mapPath)) {
            CustomLogger::note($note, "File exists: " . self::$mapPath, "VaultHelper:22");
            self::$map = json_decode(File::get(self::$mapPath), true);
            if ($showPrint_r) {
                CustomLogger::note($note, "Map loaded: " . print_r(self::$map, true), "VaultHelper:24");
            }
        } else {
            CustomLogger::note($note, "File does not exist: " . self::$mapPath, "VaultHelper:26");
            self::$map = [];
        }
    }

    /**
     * Get the original display name for a normalized path.
     * Path should be relative to vault root, e.g. "dungeon/level-1/room.md"
     */
    public static function getOriginalName($normalizedPath, $note, $debug = false)
    {
        self::loadMap($note);

        $parts = explode('/', $normalizedPath);
        $currentNode = self::$map;
        $originalName = basename($normalizedPath); // Fallback

        // Traverse the tree
        // The tree structure:
        // { "directories": { "subdir": { ... } }, "files": { "file.md": "Original" } }

        $count = count($parts);
        for ($i = 0; $i < $count; $i++) {
            $part = $parts[$i];
            $lowerPart = strtolower($part);
            $isLast = ($i === $count - 1);

            if ($isLast) {
                // Look in 'files'
                if (isset($currentNode['files'][$lowerPart])) {
                    if ($debug)
                        CustomLogger::note($note, 'trovato file/lowerPart: ' . $lowerPart, "VaultHelper:56");
                    return $currentNode['files'][$lowerPart];
                }
            } else {
                if ($debug)
                    CustomLogger::note($note, "cerco di entrare in directory/lowerPart: " . $lowerPart, "VaultHelper:59");
                // Look in 'directories'
                if (isset($currentNode['directories'][$lowerPart])) {
                    if ($debug)
                        CustomLogger::note($note, "trovata directory/lowerPart: " . $lowerPart, "VaultHelper:62");
                    $currentNode = $currentNode['directories'][$lowerPart];
                } else {
                    // Path not found in map
                    if ($debug)
                        CustomLogger::note($note, 'non trovata directory/lowerPart: ' . $lowerPart, "VaultHelper:66");
                    return self::prettify($originalName);
                }
            }
        }

        return self::prettify($originalName);
    }

    /**
     * Get the original display name for a normalized path.
     * Path should be relative to vault root, e.g. "dungeon/level-1/room.md"
     */
    public static function getNormalizedName($normalizedPath, $note)
    {
        self::loadMap($note);

        $parts = explode('/', $normalizedPath);
        $currentNode = self::$map;
        $originalName = basename($normalizedPath); // Fallback

        // Traverse the tree
        // The tree structure:
        // { "directories": { "subdir": { ... } }, "files": { "file.md": "Original" } }

        $count = count($parts);
        for ($i = 0; $i < $count; $i++) {
            $part = $parts[$i];
            $lowerPart = strtolower($part);
            $isLast = ($i === $count - 1);

            if ($isLast) {
                // Look in 'files'
                if (isset($currentNode['files'][$lowerPart])) {
                    return $currentNode['files'][$lowerPart];
                }
            } else {
                // Look in 'directories'
                if (isset($currentNode['directories'][$lowerPart])) {
                    $currentNode = $currentNode['directories'][$lowerPart];
                } else {
                    // Path not found in map
                    return self::prettify($originalName);
                }
            }
        }

        return self::prettify($originalName);
    }

    /**
     * Get the original name of a directory itself.
     */
    public static function getOriginalDirectoryName($dirPath, $note)
    {
        self::loadMap($note);
        $parts = explode('/', $dirPath);
        $currentNode = self::$map;

        // CustomLogger::note($note, "dirPath: " . $dirPath, "VaultHelper:126");
        // CustomLogger::note($note, "parts: " . print_r($parts, true), "VaultHelper:127");
        // CustomLogger::note($note, "currentNode: " . print_r($currentNode, true), "VaultHelper:128");

        foreach ($parts as $part) {
            $lowerPart = strtolower($part);
            if (isset($currentNode['directories'][$lowerPart])) {
                CustomLogger::note($note, "lowerPart: " . $lowerPart, "VaultHelper:133");
                // CustomLogger::note($note, "currentNode: " . print_r($currentNode, true) . "\ndiventa " . print_r($currentNode['directories'][$lowerPart], true), "VaultHelper:89");
                $currentNode = $currentNode['directories'][$lowerPart];
            } else {
                CustomLogger::note($note, "Path not found in map: " . $part, "VaultHelper:137");
                CustomLogger::note($note, "ritorno di getOriginalDirectoryName($dirPath): " . self::prettify($part), "VaultHelper:138");
                return self::prettify($part);
            }
        }
        CustomLogger::note($note, "ritorno di getOriginalDirectoryName($dirPath): " . $currentNode['original'] ?? self::prettify(end($parts)), "VaultHelper:142");
        return $currentNode['original'] ?? self::prettify(end($parts));
    }

    /**
     * Search for notes by their original title.
     * Returns an array of results: [['original' => '...', 'path' => '...'], ...]
     */
    public static function searchNotes($query, $note)
    {
        self::loadMap($note);
        $results = [];
        $query = strtolower($query);

        if (empty($query)) {
            return $results;
        }

        self::recursiveSearch(self::$map, '', $query, $results, $note);

        return $results;
    }

    protected static function recursiveSearch($node, $currentPath, $query, &$results, $note)
    {
        // Search files in current directory
        if (isset($node['files'])) {
            foreach ($node['files'] as $normalizedName => $originalName) {
                if (str_contains(strtolower($originalName), $query)) {
                    CustomLogger::note($note, "originalName=>" . $originalName);
                    CustomLogger::note($note, "normalizedName=>" . $normalizedName);
                    CustomLogger::note($note, "currentPath=>" . $currentPath);
                    if (str_ends_with($normalizedName, "md")) {
                        $results[] = [
                            'original' => $originalName,
                            'path' => $currentPath ? $currentPath . '/' . $normalizedName : $normalizedName,
                        ];
                    }
                }
            }
        }

        // Recursively search directories
        if (isset($node['directories'])) {
            foreach ($node['directories'] as $dirName => $dirNode) {
                $newPath = $currentPath ? $currentPath . '/' . $dirName : $dirName;
                self::recursiveSearch($dirNode, $newPath, $query, $results, $note);
            }
        }
    }

    protected static function prettify($slug)
    {
        $name = basename($slug, '.md');
        return ucwords(str_replace('-', ' ', $name));
    }
}
