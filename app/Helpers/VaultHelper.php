<?php

namespace App\Helpers;

use App\Models\Campaign;
use App\Services\CustomLogger;
use Illuminate\Support\Facades\File;

class VaultHelper
{
    protected static array $maps = [];

    /**
     * Risolve il nome della cartella della campagna (stringa).
     */
    public static function resolveCampaignFolder(Campaign|string|null $campaign = null): string
    {
        if ($campaign instanceof Campaign) {
            return $campaign->folder_name;
        }

        if (is_string($campaign) && !empty($campaign)) {
            return $campaign;
        }

        // Fallback: se non passata, prendiamo la prima campagna per order o default 'newCampaign'
        try {
            $first = Campaign::orderBy('order')->first();
            if ($first) {
                return $first->folder_name;
            }
        } catch (\Throwable $e) {
            // DB non ancora migrato
        }

        return 'newCampaign';
    }

    protected static function loadMap(Campaign|string|null $campaign, $note, $showPrint_r = false): array
    {
        $folder = self::resolveCampaignFolder($campaign);

        if (isset(self::$maps[$folder])) {
            return self::$maps[$folder];
        }

        // Cerca map.json nella cartella della campagna
        $mapPath = base_path('Vault/' . $folder . '/.normalize/map.json');

        // Fallback su Vault/.normalize/map.json nel caso esista la vecchia struttura (retrocompatibilità)
        if (!File::exists($mapPath)) {
            $legacyPath = base_path('Vault/.normalize/map.json');
            if (File::exists($legacyPath)) {
                $mapPath = $legacyPath;
            }
        }

        if (File::exists($mapPath)) {
            CustomLogger::note($note, "File exists: " . $mapPath, "VaultHelper:loadMap");
            $data = json_decode(File::get($mapPath), true);
            self::$maps[$folder] = is_array($data) ? $data : [];
            if ($showPrint_r) {
                CustomLogger::note($note, "Map loaded for [$folder]: " . print_r(self::$maps[$folder], true), "VaultHelper:loadMap");
            }
        } else {
            CustomLogger::note($note, "File does not exist: " . $mapPath, "VaultHelper:loadMap");
            self::$maps[$folder] = [];
        }

        return self::$maps[$folder];
    }

    /**
     * Get the original display name for a normalized path.
     * Path should be relative to vault root, e.g. "dungeon/level-1/room.md"
     */
    public static function getOriginalName($normalizedPath, $note, Campaign|string|null $campaign = null, $debug = false)
    {
        $map = self::loadMap($campaign, $note);

        $parts = explode('/', str_replace('\\', '/', $normalizedPath));
        $currentNode = $map;
        $originalName = basename($normalizedPath); // Fallback

        // Traverse the tree
        $count = count($parts);
        for ($i = 0; $i < $count; $i++) {
            $part = $parts[$i];
            $lowerPart = strtolower($part);
            $isLast = ($i === $count - 1);

            if ($isLast) {
                // Look in 'files'
                if (isset($currentNode['files'][$lowerPart])) {
                    if ($debug)
                        CustomLogger::note($note, 'trovato file/lowerPart: ' . $lowerPart, "VaultHelper:getOriginalName");
                    return $currentNode['files'][$lowerPart];
                }

                $cleanLowerPart = preg_replace('/\.(md|pdf)$/i', '', $lowerPart);
                if (isset($currentNode['files'][$cleanLowerPart . '.md'])) {
                    return $currentNode['files'][$cleanLowerPart . '.md'];
                }
                if (isset($currentNode['files'][$cleanLowerPart . '.pdf'])) {
                    return $currentNode['files'][$cleanLowerPart . '.pdf'];
                }
                if (isset($currentNode['files'][$cleanLowerPart])) {
                    return $currentNode['files'][$cleanLowerPart];
                }
            } else {
                if ($debug)
                    CustomLogger::note($note, "cerco di entrare in directory/lowerPart: " . $lowerPart, "VaultHelper:getOriginalName");
                // Look in 'directories'
                if (isset($currentNode['directories'][$lowerPart])) {
                    if ($debug)
                        CustomLogger::note($note, "trovata directory/lowerPart: " . $lowerPart, "VaultHelper:getOriginalName");
                    $currentNode = $currentNode['directories'][$lowerPart];
                } else {
                    // Path not found in map
                    if ($debug)
                        CustomLogger::note($note, 'non trovata directory/lowerPart: ' . $lowerPart, "VaultHelper:getOriginalName");
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
    public static function getNormalizedName($normalizedPath, $note, Campaign|string|null $campaign = null)
    {
        $map = self::loadMap($campaign, $note);

        $parts = explode('/', str_replace('\\', '/', $normalizedPath));
        $currentNode = $map;
        $originalName = basename($normalizedPath); // Fallback

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

                $cleanLowerPart = preg_replace('/\.(md|pdf)$/i', '', $lowerPart);
                if (isset($currentNode['files'][$cleanLowerPart . '.md'])) {
                    return $currentNode['files'][$cleanLowerPart . '.md'];
                }
                if (isset($currentNode['files'][$cleanLowerPart . '.pdf'])) {
                    return $currentNode['files'][$cleanLowerPart . '.pdf'];
                }
                if (isset($currentNode['files'][$cleanLowerPart])) {
                    return $currentNode['files'][$cleanLowerPart];
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
    public static function getOriginalDirectoryName($dirPath, $note, Campaign|string|null $campaign = null)
    {
        $map = self::loadMap($campaign, $note);
        $parts = explode('/', str_replace('\\', '/', $dirPath));
        $currentNode = $map;

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
    public static function searchNotes($query, $note, Campaign|string|null $campaign = null)
    {
        $map = self::loadMap($campaign, $note);
        $results = [];
        $query = strtolower($query);

        if (empty($query)) {
            return $results;
        }

        self::recursiveSearch($map, '', $query, $results, $note);

        return $results;
    }

    protected static function recursiveSearch($node, $currentPath, $query, &$results, $note)
    {
        // Search files in current directory
        if (isset($node['files'])) {
            foreach ($node['files'] as $normalizedName => $originalName) {
                if (str_contains(strtolower($originalName), $query)) {
                    $isMd = str_ends_with(strtolower($normalizedName), "md");
                    $isPdf = str_ends_with(strtolower($normalizedName), "pdf");
                    if ($isMd || $isPdf) {
                        $results[] = [
                            'original' => $originalName,
                            'path' => $currentPath ? $currentPath . '/' . $normalizedName : $normalizedName,
                            'is_pdf' => $isPdf,
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

    public static function getMap($note, Campaign|string|null $campaign = null): array
    {
        return self::loadMap($campaign, $note);
    }

    protected static function prettify($slug)
    {
        $name = preg_replace('/\.(md|pdf)$/i', '', basename($slug));
        return ucwords(str_replace(['-', '_'], ' ', $name));
    }
}
