<?php

namespace App\Helpers;

use App\Services\CustomLogger;
use Illuminate\Support\Facades\File;

class VaultHelper
{
    protected static $map = null;
    protected static $mapPath;

    protected static function loadMap()
    {
        if (self::$map !== null) {
            return;
        }

        self::$mapPath = base_path('/Vault/.normalize/map.json');

        if (File::exists(self::$mapPath)) {
            self::$map = json_decode(File::get(self::$mapPath), true);
        } else {
            self::$map = [];
        }
    }

    /**
     * Get the original display name for a normalized path.
     * Path should be relative to vault root, e.g. "dungeon/level-1/room.md"
     */
    public static function getOriginalName($normalizedPath)
    {
        self::loadMap();

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
    public static function getOriginalDirectoryName($dirPath)
    {
        self::loadMap();
        $parts = explode('/', $dirPath);
        $currentNode = self::$map;

        foreach ($parts as $part) {
            $lowerPart = strtolower($part);
            if (isset($currentNode['directories'][$lowerPart])) {
                $currentNode = $currentNode['directories'][$lowerPart];
            } else {
                return self::prettify($part);
            }
        }

        return $currentNode['original'] ?? self::prettify(end($parts));
    }

    /**
     * Search for notes by their original title.
     * Returns an array of results: [['original' => '...', 'path' => '...'], ...]
     */
    public static function searchNotes($query)
    {
        self::loadMap();
        $results = [];
        $query = strtolower($query);

        if (empty($query)) {
            return $results;
        }

        self::recursiveSearch(self::$map, '', $query, $results);

        return $results;
    }

    protected static function recursiveSearch($node, $currentPath, $query, &$results)
    {
        // Search files in current directory
        if (isset($node['files'])) {
            foreach ($node['files'] as $normalizedName => $originalName) {
                if (str_contains(strtolower($originalName), $query)) {
                    CustomLogger::note("search=>$query", "originalName=>" . $originalName);
                    CustomLogger::note("search=>$query", "normalizedName=>" . $normalizedName);
                    CustomLogger::note("search=>$query", "currentPath=>" . $currentPath);
                    $results[] = [
                        'original' => $originalName,
                        'path' => $currentPath ? $currentPath . '/' . $normalizedName : $normalizedName,
                    ];
                }
            }
        }

        // Recursively search directories
        if (isset($node['directories'])) {
            foreach ($node['directories'] as $dirName => $dirNode) {
                $newPath = $currentPath ? $currentPath . '/' . $dirName : $dirName;
                self::recursiveSearch($dirNode, $newPath, $query, $results);
            }
        }
    }

    protected static function prettify($slug)
    {
        $name = basename($slug, '.md');
        return ucwords(str_replace('-', ' ', $name));
    }
}
