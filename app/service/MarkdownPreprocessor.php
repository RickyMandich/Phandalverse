<?php

namespace App\Services;

class MarkdownPreprocessor
{
    public static function filterMasterBlocks(string $text): string
    {
        $start = '#startMaster';
        $end   = '#endMaster';

        // Se manca start → tutto dopo è master
        if (!str_contains($text, $start)) {
            $startPos = null;
        } else {
            $startPos = strpos($text, $start);
        }

        // Se manca end → tutto dopo start è master
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
}
