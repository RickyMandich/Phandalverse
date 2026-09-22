<?php

namespace App\Services;

/**
 * Estrae dai file markdown in `Vault/materiale/manuali/stat-block/*.md` i pochi campi
 * strutturati affidabili definiti dal template
 * (C:\Users\RickyMandich\PROJECT\Phandalverse\cronacheIntrecciate\definizioni\template\stat-block.md),
 * lasciando tutto il resto (tiri salvezza, abilità, sensi, linguaggi, sfida, azioni, azioni
 * bonus, reazioni, varianti, wikilink agli incantesimi...) come testo grezzo in `notes`: le
 * stat-block reali si discostano parecchio dal template (vedi mind-flayer.md), quindi un
 * parsing rigido di ogni sezione sarebbe fragile. `notes` è già il campo che l'app usa per
 * contenuto libero renderizzato via MarkdownPreprocessor::toHtml() (vedi DmController::renderStatBlock).
 */
class StatBlockParser
{
    public static function parse(string $markdown): array
    {
        $body = self::extractBlockquoteBody($markdown);

        $name = null;
        if (preg_match('/^#{1,3}\s*(.+)$/mu', $body, $m)) {
            $name = trim($m[1]);
        }

        $subtitle = null;
        if (preg_match('/^\*([^*\n]+)\*\s*$/mu', $body, $m)) {
            $subtitle = trim($m[1]);
        }

        $ac = self::extractField($body, 'Classe Armatura');
        $hpFormula = self::extractField($body, 'Punti Vita');
        $speed = self::extractField($body, 'Velocit[aà]');

        $attributes = self::extractAttributesTable($body);

        return [
            'name' => $name,
            'subtitle' => $subtitle,
            'ac' => $ac,
            'hp_formula' => $hpFormula,
            'speed' => $speed,
            'attributes' => $attributes,
            // Contenuto completo (compreso quanto già estratto sopra) come promemoria/fallback
            // e per tutto ciò che non ha un campo strutturato dedicato.
            'notes' => trim($body),
        ];
    }

    /**
     * Le stat-block sono scritte come blockquote Obsidian (ogni riga inizia con `>`).
     * Estrae il contenuto "srotolato" del blockquote, ignorando eventuali righe fuori
     * da esso (es. un tag `#dm` prima del blocco).
     */
    protected static function extractBlockquoteBody(string $markdown): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown);
        $bodyLines = [];

        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '>')) {
                $bodyLines[] = ltrim(substr($trimmed, 1), " \t");
            }
        }

        // Se non è scritta come blockquote (nessuna riga con '>'), usa il testo così com'è.
        if (empty($bodyLines)) {
            return trim($markdown);
        }

        return trim(implode("\n", $bodyLines));
    }

    protected static function extractField(string $body, string $labelPattern): ?string
    {
        if (preg_match('/\*\*' . $labelPattern . '\*\*\s*([^\n]+)/iu', $body, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    protected static function extractAttributesTable(string $body): array
    {
        $keys = ['for', 'des', 'cos', 'int', 'sag', 'car'];

        if (!preg_match(
            '/\|\s*FOR\s*\|\s*DES\s*\|\s*COS\s*\|\s*INT\s*\|\s*SAG\s*\|\s*CAR\s*\|\s*\n\|[-:\s|]+\|\s*\n\|([^\n]+)\|/iu',
            $body,
            $m
        )) {
            return [];
        }

        $cells = array_map('trim', explode('|', trim($m[1], "| \t")));
        $attributes = [];
        foreach ($keys as $i => $key) {
            if (isset($cells[$i]) && $cells[$i] !== '') {
                $attributes[$key] = $cells[$i];
            }
        }

        return $attributes;
    }

    /**
     * Nome "pulito" da usare come nome del mostro se la nota non ha un'intestazione utilizzabile:
     * ricade sul nome del file (senza estensione), come i mostri già gestiti manualmente.
     */
    public static function fallbackNameFromFilename(string $filename): string
    {
        $name = preg_replace('/\.md$/i', '', basename($filename));
        return ucwords(str_replace(['-', '_'], ' ', $name));
    }
}
