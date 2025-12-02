<?php

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\MarkdownConverter;
use Illuminate\Support\Facades\File;

class MarkdownPreprocessor
{
    private static ?array $fileIndex = null;

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
                $name = $file->getFilenameWithoutExtension();
                $relativePath = str_replace('\\', '/', $file->getRelativePath());
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
        $cleanName = explode('#', $noteName)[0];
        $cleanName = trim($cleanName);

        if (isset($index[$cleanName])) {
            return $index[$cleanName];
        }

        return $cleanName;
    }

    public static function filterMasterBlocks(string $text): string
    {
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
            '/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/',
            function ($matches) {
                $nota = $matches[1];
                $label = $matches[2] ?? $nota;
                $path = self::findNotePath($nota);
                $url = '/vault/' . rawurlencode($path);
                return '<a href="' . $url . '" class="wikilink">' . htmlspecialchars($label) . '</a>';
            },
            $text
        );
    }

    public static function convertEmbeds(string $text): string
    {
        return preg_replace_callback(
            '/!\[\[([^\]]+)\]\]/',
            function ($matches) {
                $content = $matches[1];
                $imageExt = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp'];
                $ext = strtolower(pathinfo($content, PATHINFO_EXTENSION));

                if (in_array($ext, $imageExt)) {
                    $url = '/vault/images/' . rawurlencode($content);
                    return '<img src="' . $url . '" alt="' . htmlspecialchars($content) . '" class="wikilink-image">';
                }

                $path = self::findNotePath($content);
                $url = '/vault/' . rawurlencode($path);
                $label = explode('#', $content)[0];
                return '<div class="embed-note"><a href="' . $url . '" class="wikilink">' . htmlspecialchars($label) . '</a></div>';
            },
            $text
        );
    }

    public static function toHtml(string $text): string
    {
        $text = self::convertTags($text);
        $text = self::convertEmbeds($text);
        $text = self::convertWikilinks($text);

        $environment = new Environment([
            'renderer' => ['soft_break' => "<br />"],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new TaskListExtension());
        $environment->addExtension(new StrikethroughExtension());

        $converter = new MarkdownConverter($environment);
        return $converter->convert($text)->getContent();
    }
}
