<?php

namespace App\Helpers;

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

        self::$mapPath = base_path('../Vault/.normalize/map.json');

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
            $isLast = ($i === $count - 1);

            if ($isLast) {
                // Look in 'files'
                if (isset($currentNode['files'][$part])) {
                    return $currentNode['files'][$part];
                }
            } else {
                // Look in 'directories'
                if (isset($currentNode['directories'][$part])) {
                    $currentNode = $currentNode['directories'][$part];
                } else {
                    // Path not found in map
                    return self::prettify($part);
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
            if (isset($currentNode['directories'][$part])) {
                $currentNode = $currentNode['directories'][$part];
            } else {
                return self::prettify($part);
            }
        }

        return $currentNode['original'] ?? self::prettify(end($parts));
    }

    protected static function prettify($slug)
    {
        $name = basename($slug, '.md');
        return ucwords(str_replace('-', ' ', $name));
    }
}
