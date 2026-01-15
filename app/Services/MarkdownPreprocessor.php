<?php

namespace App\Services;

use App\Helpers\VaultHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\MarkdownConverter;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\VaultController;

class MarkdownPreprocessor
{
    private static ?array $fileIndex = null;
    private static int $embedDepth = 0;
    private static int $maxEmbedDepth = 3;

    public static function buildFileIndex(): array
    {
        if (self::$fileIndex !== null) {
            return self::$fileIndex;
        }

        self::$fileIndex = [];
        $vaultPath = base_path('Vault');
        $files = File::allFiles($vaultPath);

        foreach ($files as $file) {
            if ($file->getExtension() === 'md') {
                // If user is not master, skip files that are DM-only so they are not discoverable
                if (!Auth::check() || !Auth::isMaster()) {
                    try {
                        $fileContent = File::get($file->getPathname());
                        if (preg_match('/(?<=^|\s)#dm(?=\s|$)/i', $fileContent)) {
                            continue;
                        }
                    } catch (\Throwable $e) {
                        // If cannot read file, skip it
                        continue;
                    }
                }
                $name = strtolower($file->getFilenameWithoutExtension());
                $relativePath = str_replace('\\', '/', $file->getRelativePath());
                $fullPath = $relativePath ? $relativePath . '/' . $file->getFilenameWithoutExtension() : $file->getFilenameWithoutExtension();

                // Index by name (lowercase)
                if (!isset(self::$fileIndex[$name])) {
                    self::$fileIndex[$name] = $fullPath;
                }
                // Index by full path (lowercase)
                self::$fileIndex[strtolower($fullPath)] = $fullPath;
            }
        }

        return self::$fileIndex;
    }

    public static function findNotePath(string $noteName): string
    {
        $index = self::buildFileIndex();
        $cleanName = strtolower(trim($noteName));

        // Rimuovi estensione se presente per il lookup nell'indice
        if (str_ends_with($cleanName, '.md')) {
            $cleanName = substr($cleanName, 0, -3);
        }

        if (isset($index[$cleanName])) {
            return $index[$cleanName];
        }

        return $cleanName;
    }

    /**
     * Rimuove i blocchi master (per utenti non master)
     */
    public static function filterMasterBlocks(string $text): string
    {
        // If the file is marked as DM-only, hide the entire file for non-master users
        if (preg_match('/(?<=^|\s)#dm(?=\s|$)/i', $text)) {
            return '';
        }

        $start = '#startMaster';
        $end = '#endMaster';

        if (!str_contains($text, $start)) {
            return $text;
        }

        $startPos = strpos($text, $start);
        $endPos = str_contains($text, $end)
            ? strpos($text, $end) + strlen($end)
            : strlen($text);

        return substr($text, 0, $startPos) . substr($text, $endPos);
    }

    /**
     * Rimuove solo i marcatori #startMaster e #endMaster ma mantiene il contenuto (per master)
     */
    public static function stripMasterMarkers(string $text): string
    {
        // Replace master markers with HTML comments so the inner markdown
        // is still processed by the markdown converter. We'll wrap the
        // final converted HTML after conversion.
        $text = preg_replace('/#startMaster\s*/i', '<!--MASTER_START-->', $text);
        $text = preg_replace('/\s*#endMaster\s*/i', '<!--MASTER_END-->', $text);
        return $text;
    }

    /**
     * After markdown conversion, replace MASTER comment markers with a wrapper
     * so the resulting HTML is highlighted for masters while still allowing
     * markdown inside the section to be rendered normally.
     */
    public static function wrapMasterBlocksInHtml(string $html): string
    {
        // Replace <!--MASTER_START--> ... <!--MASTER_END--> with a wrapper
        $pattern = '/<!--MASTER_START-->(.*?)<!--MASTER_END-->/is';
        return preg_replace_callback($pattern, function ($m) {
            $inner = $m[1];
            return '<div class="master-block">' . $inner . '</div>';
        }, $html);
    }

    /**
     * Remove the #dm marker so masters don't see the tag in the rendered output
     */
    public static function stripDmMarker($text): string
    {
        return preg_replace(
            '/(?<=^|\s)#dm(?=\s|$)/i',
            '',
            $text
        );
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

    public static function convertWikilinks(string $text): string
    {
        return preg_replace_callback(
            '/\[\[([^\]|#]+)(?:#[^\]|]*)?(?:\|([^\]]+))?\]\]/',
            function ($matches) {
                $nota = $matches[1];
                $label = $matches[2] ?? $matches[0];
                $label = trim($label, '[]');
                $path = self::findNotePath($nota);
                // Converti il path in camelCase per l'URL (usiamo il path normalizzato con .md)
                $camelPath = VaultController::pathToCamelCase($path);
                $url = '/vault/' . $camelPath;
                return '<a href="' . $url . '" class="wikilink">' . htmlspecialchars($label) . '</a>';
            },
            $text
        );
    }

    /**
     * Estrae una sezione da un contenuto markdown
     * $sectionPath e' tipo "## titolo### sottotitolo"
     */
    public static function extractSection(string $content, string $sectionPath): string
    {
        if (empty($sectionPath)) {
            return $content;
        }

        // Parse section path: "##heading1###heading2" -> [['##', 'heading1'], ['###', 'heading2']]
        preg_match_all('/(#{1,6})([^#]+)/', $sectionPath, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            return $content;
        }

        $lines = explode("\n", $content);
        $result = [];
        $inSection = false;
        $targetLevel = 0;
        $currentMatch = 0;
        $matchedLevels = [];

        foreach ($lines as $line) {
            // Check if line is a heading
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $headingMatch)) {
                $level = strlen($headingMatch[1]);
                $title = trim($headingMatch[2]);

                if ($currentMatch < count($matches)) {
                    $wantedLevel = strlen($matches[$currentMatch][1]);
                    $wantedTitle = trim($matches[$currentMatch][2]);

                    if ($level === $wantedLevel && strcasecmp($title, $wantedTitle) === 0) {
                        $matchedLevels[] = $level;
                        $currentMatch++;

                        if ($currentMatch === count($matches)) {
                            $inSection = true;
                            $targetLevel = $level;
                            $result[] = $line;
                            continue;
                        }
                    }
                }

                // Se siamo in sezione e troviamo heading di livello <= target, usciamo
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
    public static function loadEmbedContent(string $embedRef, string $note, int $index): string
    {
        // Previeni ricorsione infinita
        if (self::$embedDepth >= self::$maxEmbedDepth) {
            return '<div class="embed-note embed-error"> Embed troppo annidato</div>';
        }

        // Parse: "NoteName##section###subsection"
        $parts = preg_split('/(#{1,6})/', $embedRef, 2, PREG_SPLIT_DELIM_CAPTURE);
        $noteName = trim($parts[0]);
        $sectionPath = isset($parts[1]) ? $parts[1] . ($parts[2] ?? '') : '';

        // Trova il file
        $relativePath = self::findNotePath($noteName);
        $fullPath = base_path('Vault/' . $relativePath . '.md');

        if (!File::exists($fullPath)) {
            $camelPath = VaultController::pathToCamelCase($relativePath . '.md');
            $url = '/vault/' . $camelPath;
            return '<div class="embed-note embed-missing"><a href="' . $url . '" class="wikilink"> ' . htmlspecialchars($noteName) . ' (non trovato)</a></div>';
        }

        $content = File::get($fullPath);

        // Rimuovi frontmatter YAML
        $content = preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $content);

        // Estrai sezione se specificata
        if (!empty($sectionPath)) {
            $content = self::extractSection($content, $sectionPath);
        }

        if (empty(trim($content))) {
            return '<div class="embed-note embed-empty"> Sezione vuota o non trovata</div>';
        }

        // Renderizza il contenuto (con protezione ricorsione)
        self::$embedDepth++;
        $html = self::toHtml($content, $note);
        self::$embedDepth--;

        $camelPath = VaultController::pathToCamelCase($relativePath);
        $url = '/vault/' . $camelPath;
        $embedDepth = self::$embedDepth;
        $user = Auth::check() ? Auth::user() : null;
        $showEmbedLink = $user ? $user->showEmbedLink : false;
        $collapseEmbed = $user ? $user->collapseEmbed : false;
        $title = htmlspecialchars(end(explode(VaultHelper::getOriginalName($embedRef, $note), ' ')));

        // Header sempre visibile per permettere il collapse a tutti
        $showHeader = true;

        // Lo stato iniziale dipende dalla preferenza collapseEmbed
        // Se true -> parte chiuso. Se false -> parte aperto.
        $startCollapsed = $collapseEmbed;

        // Gestione Link e Header
        if ($collapseEmbed) {
            // Se modalità collapse attiva come default, ottimizziamo per il toggle facile:
            // - Header NON ha link (per permettere click facile su titolo per toggle)
            // - Il link passa nel contenuto (se abilitato)
            $headerWithLink = false;
            $contentWithLink = $showEmbedLink;
        } else {
            // Modalità default (espansa)
            if ($showEmbedLink) {
                // Se link attivo, resta nell'header (comportamento classico)
                $headerWithLink = true;
                $contentWithLink = false;
            } else {
                // Nessun link, header serve solo per toggle
                $headerWithLink = false;
                $contentWithLink = false;
            }
        }

        $ret = "<div class='embed-note border-primary ps-4 border-start embed-depth-$embedDepth'>";

        if ($showHeader) {
            $ret .= "<div class='embed-header'>";
            // Icona toggle
            $ret .= "<i class='bi-caret-right-square collapse-icon' data-bs-toggle='collapse' data-bs-target='#embed-$index-$embedDepth'></i>";

            if ($headerWithLink) {
                $ret .= "<a href='$url' class='wikilink'> $title</a>";
            } else {
                // Titolo come toggle (fallback icona)
                $ret .= "<span style='cursor: pointer;' data-bs-toggle='collapse' data-bs-target='#embed-$index-$embedDepth'> $title</span>";
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

    public static function restoreEmbeds(string $html, array $embeds, string $note): string
    {
        foreach ($embeds as $index => $content) {
            $imageExt = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp', 'avif'];

            // Prefer to resolve the embed target on disk inside Vault.
            // This allows embeds to point to images placed anywhere in the Vault.
            $vaultBase = base_path('Vault') . DIRECTORY_SEPARATOR;
            $candidate = $vaultBase . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $content);

            $foundImagePath = null;

            // Resolve images by searching the Vault. Support:
            // - explicit relative paths like "Personaggi/NonGiocanti/perrin.png"
            // - short names like "perrin.jpg" or "perrin" (search anywhere)
            $foundImagePath = null;

            // If the content contains a folder separator, try direct resolution first
            if (strpos($content, '/') !== false || strpos($content, '\\') !== false) {
                $candidate = $vaultBase . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $content);
                if (File::exists($candidate) && is_file($candidate)) {
                    $foundImagePath = str_replace($vaultBase, '', $candidate);
                    $foundImagePath = str_replace(DIRECTORY_SEPARATOR, '/', $foundImagePath);
                } else {
                    // try appending common extensions
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
                // No path component: search the whole vault for matching filename
                try {
                    $all = File::allFiles(base_path('Vault'));
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

                        // 1. Priorità: Corrispondenza ESATTA del nome file (es. Kokytos.png)
                        if ($filenameLower === $needleName) {
                            $exactMatch = $f->getPathname();
                            break; // Trovato quello esatto, usciamo subito
                        }

                        // 2. Secondaria: Corrispondenza del solo nome (es. Kokytos)
                        // Memorizziamo il primo che troviamo come fallback
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
                } catch (\Throwable $e) {
                    // ignore search errors and fall back
                }
            }

            if ($foundImagePath !== null) {
                // Encode each path segment separately (do not encode slashes)
                $segments = explode('/', $foundImagePath);
                $enc = implode('/', array_map('rawurlencode', $segments));
                $url = '/vault/' . $enc;
                $replacement = '<img src="' . $url . '" alt="' . htmlspecialchars($foundImagePath) . '" class="wikilink-image" style="max-width: 100%; height: auto;">';
            } else {
                // Check if it has an explicit image extension
                $ext = strtolower(pathinfo($content, PATHINFO_EXTENSION));
                if (in_array($ext, $imageExt)) {
                    // Has image extension but not found → render as broken image
                    Log::warning('Embed image not found in Vault: ' . $content);
                    $segments = explode('/', $content);
                    $enc = implode('/', array_map('rawurlencode', $segments));
                    $url = '/vault/' . $enc;
                    $replacement = '<img src="' . $url . '" alt="' . htmlspecialchars($content) . '" class="wikilink-image" style="max-width: 100%; height: auto;">';
                } else {
                    // No extension or non-image extension → try as note embed
                    $replacement = self::loadEmbedContent($content, $note, $index);
                }
            }

            $html = str_replace('<!--EMBED:' . $index . '-->', $replacement, $html);
        }
        return $html;
    }

    public static function convertRomanNumbers(string $text, string $note): string
    {
        CustomLogger::note($note, "text:\n$text", "debug");
        $text = preg_replace(
            '/R\|([MDCLXVI]+)\|/',
            "<span class='roman-number'>$1</span>",
            $text
        );
        return $text;
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

                // Check if it already has target="_blank"
                if (stripos($tag, 'target=') !== false) {
                    return $tag;
                }

                // If it's the same host as the app, don't add target="_blank"
                $urlHost = parse_url($url, PHP_URL_HOST);
                if ($appHost && $urlHost === $appHost) {
                    return $tag;
                }

                // Add target="_blank" and rel="noopener noreferrer"
                return preg_replace('/<a\s+/i', '<a target="_blank" rel="noopener noreferrer" ', $tag, 1);
            },
            $html
        );
    }

    public static function toHtml(string $text, string $note): string
    {
        $embeds = [];
        $text = self::replaceEmbedsWithPlaceholders($text, $embeds);
        $text = self::convertTags($text);
        $text = self::convertWikilinks($text);
        $text = self::convertRomanNumbers($text, $note);
        // Extract master sections and replace them with placeholders so we can
        // convert the surrounding markdown as a whole, then convert each
        // master section separately to ensure inner markdown (headings, lists,
        // etc.) is rendered correctly.
        $masterBlocks = [];
        $text = preg_replace_callback('/#startMaster\s*(.*?)\s*#endMaster/s', function ($m) use (&$masterBlocks) {
            $idx = count($masterBlocks);
            $masterBlocks[$idx] = $m[1];
            // Use an HTML comment placeholder so the main markdown pass won't
            // modify the token (underscores and other chars can be mangled).
            return "<!--MASTER_BLOCK:{$idx}-->";
        }, $text);

        $environment = new Environment([
            'renderer' => ['soft_break' => "<br />"],
            'html_input' => 'allow',
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new TaskListExtension());
        $environment->addExtension(new StrikethroughExtension());

        $converter = new MarkdownConverter($environment);

        // Convert the main text (with master placeholders)
        $html = $converter->convert($text)->getContent();

        // Convert each master block separately and insert the rendered HTML
        foreach ($masterBlocks as $i => $innerMarkdown) {
            $innerHtml = $converter->convert($innerMarkdown)->getContent();
            $wrapped = '<div class="master-block">' . $innerHtml . '</div>';
            $html = str_replace("<!--MASTER_BLOCK:{$i}-->", $wrapped, $html);
        }

        // Finally, restore embeds (placeholders -> actual embed HTML)
        $html = self::restoreEmbeds($html, $embeds, $note);

        // Process external links to add target="_blank"
        $html = self::processExternalLinks($html);

        return $html;
    }
}