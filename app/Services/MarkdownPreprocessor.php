<?php

namespace App\Services;

use App\Helpers\VaultHelper;
use App\Models\Campaign;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\MarkdownConverter;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\VaultController;

class MarkdownPreprocessor
{
    private static array $fileIndices = [];
    private static int $embedDepth = 0;
    private static int $maxEmbedDepth = 3;

    public static function buildFileIndex(Campaign|string|null $campaign = null): array
    {
        $folder = VaultHelper::resolveCampaignFolder($campaign);

        if (isset(self::$fileIndices[$folder])) {
            return self::$fileIndices[$folder];
        }

        self::$fileIndices[$folder] = [];

        // Verifica path nella sottocartella della campagna
        $vaultPath = base_path('Vault/' . $folder);

        // Fallback per retrocompatibilità se Vault/{folder} non esiste ma esiste Vault/
        if (!File::exists($vaultPath) || !File::isDirectory($vaultPath)) {
            $legacyPath = base_path('Vault');
            if (File::exists($legacyPath) && File::isDirectory($legacyPath)) {
                $vaultPath = $legacyPath;
            } else {
                return self::$fileIndices[$folder];
            }
        }

        try {
            $files = File::allFiles($vaultPath);
        } catch (\Throwable $e) {
            Log::warning("Vault directory is not available for markdown index [$folder]: " . $e->getMessage());
            return self::$fileIndices[$folder];
        }

        // I wikilink si riferiscono sempre a note markdown: l'indice comprende solo i file .md,
        // i PDF restano navigabili solo da albero/sidebar/ricerca (VaultController + VaultHelper).
        foreach ($files as $file) {
            $ext = strtolower($file->getExtension());
            if ($ext === 'md') {
                $isMaster = Auth::check() && Auth::user()->isMaster();
                $shouldSkip = false;

                // If user is not master, skip files that are DM-only so they are not discoverable
                if (!$isMaster) {
                    try {
                        $fileContent = File::get($file->getPathname());
                        if (!AccessControlService::noteIsVisibleTo($fileContent, null, $campaign)) {
                            $shouldSkip = true;
                        }
                    } catch (\Throwable $e) {
                        $shouldSkip = true;
                    }
                }

                if ($shouldSkip) {
                    continue;
                }

                $name = strtolower($file->getFilenameWithoutExtension());
                $relativePath = str_replace('\\', '/', $file->getRelativePath());
                $fullPath = $relativePath ? $relativePath . '/' . $file->getFilenameWithoutExtension() : $file->getFilenameWithoutExtension();

                // Index by name (lowercase)
                if (!isset(self::$fileIndices[$folder][$name])) {
                    self::$fileIndices[$folder][$name] = $fullPath;
                }
                // Index by full path (lowercase)
                self::$fileIndices[$folder][strtolower($fullPath)] = $fullPath;
            }
        }

        return self::$fileIndices[$folder];
    }

    public static function findNotePath(string $noteName, Campaign|string|null $campaign = null): string
    {
        $index = self::buildFileIndex($campaign);
        $cleanName = strtolower(trim(str_replace('\\', '/', $noteName)));
        $cleanName = rtrim($cleanName, '/');

        // Rimuovi estensione se presente per il lookup nell'indice
        if (str_ends_with($cleanName, '.md')) {
            $cleanName = substr($cleanName, 0, -3);
        } elseif (str_ends_with($cleanName, '.pdf')) {
            $cleanName = substr($cleanName, 0, -4);
        }

        if (isset($index[$cleanName])) {
            return $index[$cleanName];
        }

        return $cleanName;
    }

    /**
     * Rimuove i blocchi master (per utenti non master)
     */
    public static function filterMasterBlocks(string $text, Campaign|string|null $campaign = null): string
    {
        // Se l'utente è master, non filtriamo nulla (i marker verranno gestiti dal renderer)
        if (Auth::check() && Auth::user()->isMaster()) {
            return $text;
        }

        // Nota non visibile (DM-only o gruppo mancante): nascondi tutto
        if (!AccessControlService::noteIsVisibleTo($text, null, $campaign)) {
            return '';
        }

        // Rimuove tutti i blocchi compresi tra #startMaster e #endMaster (globale)
        $text = preg_replace('/#startMaster\s*(.*?)\s*#endMaster/is', '', $text);

        // Rimuove i blocchi #startAccess:gruppo ... #endAccess per cui l'utente non ha accesso
        $text = AccessControlService::filterAccessBlocks($text);

        return $text;
    }

    /**
     * Rimuove solo i marcatori #startMaster e #endMaster ma mantiene il contenuto (per master)
     */
    public static function stripMasterMarkers(string $text): string
    {
        $text = preg_replace('/#startMaster\s*/i', '<!--MASTER_START-->', $text);
        $text = preg_replace('/\s*#endMaster\s*/i', '<!--MASTER_END-->', $text);
        return $text;
    }

    /**
     * After markdown conversion, replace MASTER comment markers with a wrapper
     */
    public static function wrapMasterBlocksInHtml(string $html): string
    {
        $pattern = '/<!--MASTER_START-->(.*?)<!--MASTER_END-->/is';
        return preg_replace_callback($pattern, function ($m) {
            $inner = $m[1];
            return '<div class="master-block" data-access-type="master">' . $inner . '</div>';
        }, $html);
    }

    /**
     * Remove the #dm marker so masters don't see the tag in the rendered output
     */
    public static function stripDmMarker($text): string
    {
        return preg_replace('/(?<=^|\s)#dm(?=\s|$)/i', '', $text);
    }

    public static function convertTags(string $text): string
    {
        return preg_replace_callback(
            '/(?<=^|\s)#([a-zA-Z][a-zA-Z0-9_-]*)(?=\s|$)/m',
            function ($matches) {
                $tag = $matches[1];
                return '<span class="obsidian-tag">#' . htmlspecialchars($tag) . '</span>';
            },
            $text
        );
    }

    public static function convertWikilinks(string $text, string $note = '', Campaign|string|null $campaign = null): string
    {
        $folder = VaultHelper::resolveCampaignFolder($campaign);

        return preg_replace_callback(
            '/\[\[([^\]|#]+)(?:#[^\]|]*)?(?:\|([^\]]+))?\]\]/',
            function ($matches) use ($note, $campaign, $folder) {
                $nota = trim($matches[1]);
                $index = self::buildFileIndex($campaign);
                $cleanName = strtolower(trim(str_replace('\\', '/', $nota)));
                $cleanName = rtrim($cleanName, '/');

                // Rimuovi estensione se presente per il lookup nell'indice
                if (str_ends_with($cleanName, '.md')) {
                    $cleanName = substr($cleanName, 0, -3);
                } elseif (str_ends_with($cleanName, '.pdf')) {
                    $cleanName = substr($cleanName, 0, -4);
                }

                $found = false;
                $path = $cleanName;
                if (isset($index[$cleanName])) {
                    $path = $index[$cleanName];
                    $found = true;
                }

                if (isset($matches[2]) && !empty(trim($matches[2]))) {
                    $label = trim($matches[2]);
                } else {
                    // Se non c'è alias, prova a prendere il nome originale dalla mappa della campagna
                    $label = VaultHelper::getOriginalName($path, $note, $campaign);
                }

                // Genera il link solo se la nota è nell'indice (quindi è pubblica o l'utente ha accesso)
                if (!$found) {
                    return htmlspecialchars($label);
                }

                $camelPath = VaultController::pathToCamelCase($path);
                $url = '/vault/' . $folder . '/' . $camelPath;
                return '<a href="' . $url . '" class="wikilink">' . htmlspecialchars($label) . '</a>';
            },
            $text
        );
    }

    /**
     * Estrae una sezione da un contenuto markdown
     */
    public static function extractSection(string $content, string $sectionPath): string
    {
        if (empty($sectionPath)) {
            return $content;
        }

        preg_match_all('/(#{1,6})([^#]+)/', $sectionPath, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            return $content;
        }

        $lines = explode("\n", $content);
        $result = [];
        $inSection = false;
        $targetLevel = 0;
        $currentMatch = 0;

        foreach ($lines as $line) {
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $headingMatch)) {
                $level = strlen($headingMatch[1]);
                $title = trim($headingMatch[2]);

                if ($currentMatch < count($matches)) {
                    $wantedLevel = strlen($matches[$currentMatch][1]);
                    $wantedTitle = trim($matches[$currentMatch][2]);

                    if ($level === $wantedLevel && strcasecmp($title, $wantedTitle) === 0) {
                        $currentMatch++;

                        if ($currentMatch === count($matches)) {
                            $inSection = true;
                            $targetLevel = $level;
                            $result[] = $line;
                            continue;
                        }
                    }
                }

                if ($inSection && $level <= $targetLevel) {
                    break;
                }
            }

            if ($inSection) {
                $result[] = $line;
            }
        }

        return implode("\n", $result);
    }

    /**
     * Carica e renderizza il contenuto di un embed
     */
    public static function loadEmbedContent(string $embedRef, string $note, int $index, Campaign|string|null $campaign = null, bool $debug = false): string
    {
        $folder = VaultHelper::resolveCampaignFolder($campaign);

        if (self::$embedDepth >= self::$maxEmbedDepth) {
            return '<div class="embed-note embed-error"> Embed troppo annidato</div>';
        }

        $parts = preg_split('/(#{1,6})/', $embedRef, 2, PREG_SPLIT_DELIM_CAPTURE);
        $noteName = trim($parts[0]);
        $sectionPath = isset($parts[1]) ? $parts[1] . ($parts[2] ?? '') : '';

        $relativePath = self::findNotePath($noteName, $campaign);
        $fullPath = base_path('Vault/' . $folder . '/' . $relativePath . '.md');

        if (!File::exists($fullPath)) {
            // Fallback legacy
            $legacyFullPath = base_path('Vault/' . $relativePath . '.md');
            if (File::exists($legacyFullPath)) {
                $fullPath = $legacyFullPath;
            }
        }

        if (!File::exists($fullPath)) {
            $camelPath = VaultController::pathToCamelCase($relativePath . '.md');
            $url = '/vault/' . $folder . '/' . $camelPath;
            return '<div class="embed-note embed-missing"><a href="' . $url . '" class="wikilink"> ' . htmlspecialchars($noteName) . ' (non trovato)</a></div>';
        }

        $content = File::get($fullPath);

        if (!AccessControlService::noteIsVisibleTo($content, null, $campaign)) {
            return '<div class="embed-note embed-restricted text-muted fst-italic"><i class="bi bi-lock"></i> Contenuto riservato</div>';
        }

        $content = preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $content);

        if (!empty($sectionPath)) {
            $content = self::extractSection($content, $sectionPath);
        }

        if (empty(trim($content))) {
            return '<div class="embed-note embed-empty"> Sezione vuota o non trovata</div>';
        }

        self::$embedDepth++;
        $html = self::toHtml($content, $note, $campaign);
        self::$embedDepth--;

        $camelPath = VaultController::pathToCamelCase($relativePath);
        $url = '/vault/' . $folder . '/' . $camelPath;
        $embedDepth = self::$embedDepth;
        $user = Auth::check() ? Auth::user() : null;
        $showEmbedLink = $user ? $user->showEmbedLink : false;
        $collapseEmbed = $user ? $user->collapseEmbed : false;

        $title = VaultHelper::getOriginalName($embedRef, $note, $campaign);
        $title = explode('#', $title);
        $title = htmlspecialchars(end($title));

        $showHeader = true;
        $startCollapsed = $collapseEmbed;

        if ($collapseEmbed) {
            $headerWithLink = false;
            $contentWithLink = $showEmbedLink;
        } else {
            if ($showEmbedLink) {
                $headerWithLink = true;
                $contentWithLink = false;
            } else {
                $headerWithLink = false;
                $contentWithLink = false;
            }
        }

        $ret = "<div class='embed-note border-warning ps-3 border-start embed-depth-$embedDepth'>";

        if ($showHeader) {
            $ret .= "<div class='embed-header'>";
            $ret .= "<i class='bi-caret-right-square collapse-icon' data-bs-toggle='collapse' data-bs-target='#embed-$index-$embedDepth'></i>";

            if ($headerWithLink) {
                $ret .= "<a href='$url' class='wikilink'> $title</a>";
            } else {
                $ret .= "<span style='cursor: pointer;' data-bs-toggle='collapse' data-bs-target='#embed-$index-$embedDepth' class='embed-title'> $title</span>";
            }
            $ret .= "</div>";
        }

        $ret .= "<div class='embed-content collapse";
        if (!$startCollapsed) {
            $ret .= " show";
        }
        $ret .= "' id='embed-$index-$embedDepth'>";

        if ($contentWithLink) {
            $ret .= "<div class='mb-2'><a href='$url' class='wikilink'>$title</a></div>";
        }

        $ret .= $html . '</div></div>';
        return $ret;
    }

    public static function replaceEmbedsWithPlaceholders(string $text, array &$embeds): string
    {
        return preg_replace_callback(
            '/!\[\[([^\]]+)\]\]/',
            function ($matches) use (&$embeds) {
                $content = $matches[1];
                $placeholder = '<!--EMBED:' . count($embeds) . '-->';
                $embeds[] = $content;
                return $placeholder;
            },
            $text
        );
    }

    public static function restoreEmbeds(string $html, array $embeds, string $note, Campaign|string|null $campaign = null): string
    {
        $folder = VaultHelper::resolveCampaignFolder($campaign);

        foreach ($embeds as $index => $content) {
            $imageExt = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp', 'avif'];

            $vaultBase = base_path('Vault/' . $folder) . DIRECTORY_SEPARATOR;

            // Fallback se Vault/{folder} non esiste
            if (!File::isDirectory($vaultBase)) {
                $vaultBase = base_path('Vault') . DIRECTORY_SEPARATOR;
            }

            $candidate = $vaultBase . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $content);
            $foundImagePath = null;

            if (strpos($content, '/') !== false || strpos($content, '\\') !== false) {
                $candidate = $vaultBase . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $content);
                if (File::exists($candidate) && is_file($candidate)) {
                    $foundImagePath = str_replace($vaultBase, '', $candidate);
                    $foundImagePath = str_replace(DIRECTORY_SEPARATOR, '/', $foundImagePath);
                } else {
                    foreach ($imageExt as $ext) {
                        $try = $candidate . '.' . $ext;
                        if (File::exists($try) && is_file($try)) {
                            $foundImagePath = str_replace($vaultBase, '', $try);
                            $foundImagePath = str_replace(DIRECTORY_SEPARATOR, '/', $foundImagePath);
                            break;
                        }
                    }
                }
            } else {
                try {
                    if (File::isDirectory($vaultBase)) {
                        $all = File::allFiles($vaultBase);
                        $needleName = strtolower($content);
                        $needleNoExt = strtolower(pathinfo($content, PATHINFO_FILENAME));

                        $exactMatch = null;
                        $fuzzyMatch = null;

                        foreach ($all as $f) {
                            $extFound = strtolower($f->getExtension());
                            if (!in_array($extFound, $imageExt)) {
                                continue;
                            }

                            $filenameLower = strtolower($f->getFilename());

                            if ($filenameLower === $needleName) {
                                $exactMatch = $f->getPathname();
                                break;
                            }

                            if ($fuzzyMatch === null && $needleNoExt !== '') {
                                if (strtolower($f->getBasename('.' . $f->getExtension())) === $needleNoExt) {
                                    $fuzzyMatch = $f->getPathname();
                                }
                            }
                        }

                        $finalPath = $exactMatch ?? $fuzzyMatch;

                        if ($finalPath) {
                            $foundImagePath = str_replace($vaultBase, '', $finalPath);
                            $foundImagePath = str_replace(DIRECTORY_SEPARATOR, '/', $foundImagePath);
                        }
                    }
                } catch (\Throwable $e) {
                }
            }

            if ($foundImagePath !== null) {
                $segments = explode('/', $foundImagePath);
                $enc = implode('/', array_map('rawurlencode', $segments));
                $url = '/vault/' . $folder . '/' . $enc;
                $replacement = '<img src="' . $url . '" alt="' . htmlspecialchars($foundImagePath) . '" class="wikilink-image" style="max-width: 100%; height: auto;">';
            } else {
                $ext = strtolower(pathinfo($content, PATHINFO_EXTENSION));
                if (in_array($ext, $imageExt)) {
                    $segments = explode('/', $content);
                    $enc = implode('/', array_map('rawurlencode', $segments));
                    $url = '/vault/' . $folder . '/' . $enc;
                    $replacement = '<img src="' . $url . '" alt="' . htmlspecialchars($content) . '" class="wikilink-image" style="max-width: 100%; height: auto;">';
                } else {
                    // I wikilink (anche negli embed) si riferiscono sempre a note markdown: i PDF non sono embeddabili qui.
                    $replacement = self::loadEmbedContent($content, $note, $index, $campaign);
                }
            }

            $html = str_replace('<!--EMBED:' . $index . '-->', $replacement, $html);
        }
        return $html;
    }

    public static function convertRomanNumbers(string $text, string $note): string
    {
        return preg_replace(
            '/R\|([MDCLXVI]+)\|/',
            "<span class='roman-number'>$1</span>",
            $text
        );
    }

    public static function processExternalLinks(string $html): string
    {
        $appUrl = config('app.url');
        $appHost = parse_url($appUrl, PHP_URL_HOST);

        return preg_replace_callback(
            '/<a\s+([^>]*href=["\'](https?:\/\/[^"\']+)["\'][^>]*)>/i',
            function ($matches) use ($appHost) {
                $tag = $matches[0];
                $url = $matches[2];

                if (stripos($tag, 'target=') !== false) {
                    return $tag;
                }

                $urlHost = parse_url($url, PHP_URL_HOST);
                if ($appHost && $urlHost === $appHost) {
                    return $tag;
                }

                return preg_replace('/<a\s+/i', '<a target="_blank" rel="noopener noreferrer" ', $tag, 1);
            },
            $html
        );
    }

    public static function toHtml(string $text, string $note, Campaign|string|null $campaign = null): string
    {
        $isMaster = Auth::check() && Auth::user()->isMaster();
        $user = Auth::user();

        // 1. Verifica visibilità nota
        if (!AccessControlService::noteIsVisibleTo($text, $user, $campaign)) {
            return '';
        }

        // 2. Puliamo il tag #access:... dal testo visualizzato
        $text = AccessControlService::stripAccessTags($text);

        // 3. Puliamo eventuale #dm
        if ($isMaster) {
            $text = self::stripDmMarker($text);
        }

        // 4. Estrai i blocchi master
        $masterBlocks = [];
        $text = preg_replace_callback('/#startMaster\s*(.*?)\s*#endMaster/is', function ($m) use (&$masterBlocks, $isMaster) {
            if (!$isMaster) {
                return '';
            }
            $idx = count($masterBlocks);
            $masterBlocks[$idx] = $m[1];
            return "<!--MASTER_BLOCK:{$idx}-->";
        }, $text);

        // 5. Estrai i blocchi access
        $accessBlocks = [];
        $campaignId = ($campaign instanceof Campaign) ? $campaign->id : null;

        $text = preg_replace_callback('/#startAccess-([a-z0-9]+(?:_[a-z0-9]+)*)\s*(.*?)\s*#endAccess/is', function ($m) use (&$accessBlocks, $user, $isMaster, $campaignId) {
            $requiredGroups = array_map('strtolower', explode('_', $m[1]));
            $hasAccess = $isMaster || ($user && $user->hasAccessToAnyGroup($requiredGroups));

            if (!$hasAccess) {
                return '';
            }

            $idx = count($accessBlocks);
            $badges = AccessControlService::computeBadgeGroups($user, $requiredGroups, $campaignId);
            $color = AccessControlService::resolveBlockColor($requiredGroups, $campaignId);

            $accessBlocks[$idx] = [
                'content' => $m[2],
                'badges' => $badges,
                'color' => $color,
                'groups' => $requiredGroups,
            ];

            return "<!--ACCESS_BLOCK:{$idx}-->";
        }, $text);

        $embeds = [];

        $processContent = function (string $t) use (&$embeds, $note, $campaign) {
            $t = self::replaceEmbedsWithPlaceholders($t, $embeds);
            $t = self::convertTags($t);
            $t = self::convertWikilinks($t, $note, $campaign);
            $t = self::convertRomanNumbers($t, $note);
            return $t;
        };

        $text = $processContent($text);

        $environment = new Environment([
            'renderer' => ['soft_break' => "<br />"],
            'html_input' => 'allow',
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new TaskListExtension());
        $environment->addExtension(new StrikethroughExtension());

        $converter = new MarkdownConverter($environment);

        $html = $converter->convert($text)->getContent();

        foreach ($masterBlocks as $i => $innerMarkdown) {
            $innerMarkdown = $processContent($innerMarkdown);
            $innerHtml = $converter->convert($innerMarkdown)->getContent();
            $wrapped = '<div class="master-block" data-access-type="master">' . $innerHtml . '</div>';
            $html = str_replace("<!--MASTER_BLOCK:{$i}-->", $wrapped, $html);
        }

        foreach ($accessBlocks as $i => $blockData) {
            $innerMarkdown = $processContent($blockData['content']);
            $innerHtml = $converter->convert($innerMarkdown)->getContent();

            $badgesHtml = '';
            if (!empty($blockData['badges'])) {
                $badgesHtml = '<div class="access-badges mb-2">';
                foreach ($blockData['badges'] as $b) {
                    $bColor = htmlspecialchars($b['color']);
                    $bName = htmlspecialchars($b['name']);
                    $badgesHtml .= "<span class=\"badge me-1\" style=\"background-color: {$bColor}; color: #fff;\">{$bName}</span>";
                }
                $badgesHtml .= '</div>';
            }

            $color = htmlspecialchars($blockData['color']);
            $groupsAttr = htmlspecialchars(implode(',', $blockData['groups'] ?? []));
            $wrapped = "<div class=\"access-block\" data-access-type=\"group\" data-groups=\"{$groupsAttr}\" style=\"--access-color: {$color}; border-left: 3px solid {$color}; padding: 0.5rem 1rem; margin: 1rem 0; background: rgba(255,255,255,0.03); border-radius: 4px;\">{$badgesHtml}{$innerHtml}</div>";
            $html = str_replace("<!--ACCESS_BLOCK:{$i}-->", $wrapped, $html);
        }

        $html = self::restoreEmbeds($html, $embeds, $note, $campaign);
        $html = self::processExternalLinks($html);
        $html = str_replace('<blockquote>', '<blockquote class="stat-block">', $html);

        return $html;
    }
}