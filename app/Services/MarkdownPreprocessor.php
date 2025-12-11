<?php

namespace App\Services;

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
                if (!\Illuminate\Support\Facades\Auth::check() || !\Illuminate\Support\Facades\Auth::isMaster()) {
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
                $name = $file->getFilenameWithoutExtension();
                $relativePath = str_replace('\\', '/', $file->getRelativePath());

                // Fix encoding per caratteri speciali (es. à, è, ò, ù)
                if (!mb_check_encoding($name, 'UTF-8')) {
                    $name = mb_convert_encoding($name, 'UTF-8', 'ISO-8859-1');
                }
                if (!mb_check_encoding($relativePath, 'UTF-8')) {
                    $relativePath = mb_convert_encoding($relativePath, 'UTF-8', 'ISO-8859-1');
                }

                if ($relativePath) {
                    self::$fileIndex[$name] = $relativePath . '/' . $name;
                } else {
                    self::$fileIndex[$name] = $name;
                }
            }
        }

        return self::$fileIndex;
    }

    public static function findNotePath(string $noteName): string
    {
        $index = self::buildFileIndex();
        $cleanName = trim($noteName);

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
        $end   = '#endMaster';

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
            return '<span class="master-block">' . $inner . '</span>';
        }, $html);
    }

    /**
     * Remove the #dm marker so masters don't see the tag in the rendered output
     */
    public static function stripDmMarker(string $text): string
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

    public static function convertWikilinks(string $text): string
    {
        return preg_replace_callback(
            '/\[\[([^\]|#]+)(?:#[^\]|]*)?(?:\|([^\]]+))?\]\]/',
            function ($matches) {
                $nota = $matches[1];
                $label = $matches[2] ?? $matches[0];
                $label = trim($label, '[]');
                $path = self::findNotePath($nota);
                // Converti il path in camelCase per l'URL
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
    public static function loadEmbedContent(string $embedRef): string
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
            $camelPath = VaultController::pathToCamelCase($relativePath);
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
        $html = self::toHtml($content);
        self::$embedDepth--;

        $camelPath = VaultController::pathToCamelCase($relativePath);
        $url = '/vault/' . $camelPath;
        $embedDepth = self::$embedDepth;
        $ret = "<div class='embed-note border-primary ps-4 border-start embed-depth-$embedDepth'>";
        if(Auth::check() && Auth::user()->showEmbedLink){
            $ret = "$ret<div class='embed-header'><a href='$url' clas='wikilink'> " . htmlspecialchars($embedRef) . '</a></div>';
        }
        $ret = $ret.'<div class="embed-content">' . $html . '</div></div>';
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

    public static function restoreEmbeds(string $html, array $embeds): string
    {
        foreach ($embeds as $index => $content) {
            $imageExt = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp'];

            // Prefer to resolve the embed target on disk inside Vault.
            // This allows embeds to point to images placed anywhere in the Vault.
            $vaultBase = base_path('Vault') . DIRECTORY_SEPARATOR;
            $candidate = $vaultBase . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $content);

            $foundImagePath = null;

            // If the exact path exists and is a file, check its extension
            if (File::exists($candidate) && is_file($candidate)) {
                $foundExt = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
                if (in_array($foundExt, $imageExt)) {
                    $foundImagePath = $content;
                }
            }

            // Otherwise, try appending common image extensions (allow referencing without ext)
            if ($foundImagePath === null) {
                foreach ($imageExt as $ext) {
                    $try = $candidate . '.' . $ext;
                    if (File::exists($try) && is_file($try)) {
                        // build relative path with the appended extension
                        $foundImagePath = $content . '.' . $ext;
                        break;
                    }
                }
            }

            if ($foundImagePath !== null) {
                $url = '/vault/raw/' . rawurlencode($foundImagePath);
                $replacement = '<img src="' . $url . '" alt="' . htmlspecialchars($foundImagePath) . '" class="wikilink-image">';
            } else {
                // Not an image file on disk: fallback to previous logic
                $ext = strtolower(pathinfo($content, PATHINFO_EXTENSION));
                if (in_array($ext, $imageExt)) {
                    // Even if file not found on disk, keep backward-compatible behavior
                    $url = '/vault/raw/' . rawurlencode($content);
                    $replacement = '<img src="' . $url . '" alt="' . htmlspecialchars($content) . '" class="wikilink-image">';
                } else {
                    $replacement = self::loadEmbedContent($content);
                }
            }

            $html = str_replace('<!--EMBED:' . $index . '-->', $replacement, $html);
        }
        return $html;
    }

    public static function toHtml(string $text): string
    {
        $embeds = [];
        $text = self::replaceEmbedsWithPlaceholders($text, $embeds);
        $text = self::convertTags($text);
        $text = self::convertWikilinks($text);
        // Extract master sections and replace them with placeholders so we can
        // convert the surrounding markdown as a whole, then convert each
        // master section separately to ensure inner markdown (headings, lists,
        // etc.) is rendered correctly.
        $masterBlocks = [];
        $text = preg_replace_callback('/#startMaster\s*(.*?)\s*#endMaster/s', function ($m) use (&$masterBlocks) {
            $idx = count($masterBlocks);
            $masterBlocks[$idx] = $m[1];
            return "___MASTER_BLOCK_{$idx}___";
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
            $wrapped = '<span class="master-block">' . $innerHtml . '</span>';
            $html = str_replace("___MASTER_BLOCK_{$i}___", $wrapped, $html);
        }

        // Finally, restore embeds (placeholders -> actual embed HTML)
        $html = self::restoreEmbeds($html, $embeds);

        return $html;
    }
}