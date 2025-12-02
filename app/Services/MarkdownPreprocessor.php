<?php

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownPreprocessor
{
    /**
     * Rimuove i blocchi master (#startMaster ... #endMaster)
     */
    public static function filterMasterBlocks(string $text): string
    {
        $start = '#startMaster';
        $end   = '#endMaster';

        if (!str_contains($text, $start)) {
            $startPos = null;
        } else {
            $startPos = strpos($text, $start);
        }

        if (!str_contains($text, $end)) {
            $endPos = strlen($text);
        } else {
            $endPos = strpos($text, $end) + strlen($end);
        }

        if ($startPos !== null) {
            $before = substr($text, 0, $startPos);
            $after  = substr($text, $endPos);
            return $before . $after;
        }

        return $text;
    }

    /**
     * Converte wikilink [[Nota]] e [[Nota|Testo]] in link HTML
     */
    public static function convertWikilinks(string $text): string
    {
        return preg_replace_callback(
            '/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/',
            function ($matches) {
                $nota = $matches[1];
                $label = $matches[2] ?? $nota;
                $url = '/vault/' . rawurlencode($nota);
                return '<a href="' . $url . '" class="wikilink">' . htmlspecialchars($label) . '</a>';
            },
            $text
        );
    }

    /**
     * Converte embed ![[Nota]] in contenuto embedded
     */
    public static function convertEmbeds(string $text): string
    {
        return preg_replace_callback(
            '/!\[\[([^\]]+)\]\]/',
            function ($matches) {
                $content = $matches[1];
                $imageExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp'];
                $extension = strtolower(pathinfo($content, PATHINFO_EXTENSION));

                if (in_array($extension, $imageExtensions)) {
                    $url = '/vault/images/' . rawurlencode($content);
                    return '<img src="' . $url . '" alt="' . htmlspecialchars($content) . '" class="wikilink-image">';
                }

                $notaParts = explode('#', $content);
                $nota = $notaParts[0];
                $url = '/vault/' . rawurlencode($nota);
                return '<div class="embed-note"><a href="' . $url . '" class="wikilink">📄 ' . htmlspecialchars($content) . '</a></div>';
            },
            $text
        );
    }

    /**
     * Converte Markdown in HTML con supporto Obsidian
     */
    public static function toHtml(string $text): string
    {
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
