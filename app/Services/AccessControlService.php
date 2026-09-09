<?php

namespace App\Services;

use App\Models\AccessGroup;
use App\Models\Campaign;
use App\Models\User;
use App\Helpers\VaultHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class AccessControlService
{
    private static array $groupsCache = [];

    /**
     * Cache dei gruppi caricati per la request corrente (opzionalmente filtrati per campaignId).
     */
    public static function getAllGroups(?int $campaignId = null): Collection
    {
        $cacheKey = $campaignId !== null ? "campaign_{$campaignId}" : 'all';

        if (!isset(self::$groupsCache[$cacheKey])) {
            try {
                $query = AccessGroup::with('parent');
                if ($campaignId !== null) {
                    $query->where('campaign_id', $campaignId);
                }
                self::$groupsCache[$cacheKey] = $query->get();
            } catch (\Throwable $e) {
                self::$groupsCache[$cacheKey] = new Collection();
            }
        }
        return self::$groupsCache[$cacheKey];
    }

    public static function clearCache(): void
    {
        self::$groupsCache = [];
    }

    /**
     * Verifica se un utente ha accesso alla campagna.
     * Master ha sempre accesso.
     * Guest (null) ha accesso solo per note pubbliche (gestito in noteIsVisibleTo).
     */
    public static function userHasCampaignAccess(?User $user, Campaign|string|int $campaign): bool
    {
        if ($user && $user->isMaster()) {
            return true;
        }

        if (!$user) {
            return true; // Guest può consultare i contenuti pubblici della campagna
        }

        return $user->hasAccessToCampaign($campaign);
    }

    /**
     * Estrae gli slug dei gruppi richiesti da un tag #access-gruppo1_gruppo2
     * (nota intera). Ritorna array vuoto se il tag non è presente.
     */
    public static function requiredGroupsFromNoteTag(string $content): array
    {
        if (!preg_match('/(?<=^|\s)#access-([a-z0-9]+(?:_[a-z0-9]+)*)(?=\s|$)/i', $content, $m)) {
            return [];
        }

        return array_map('strtolower', explode('_', $m[1]));
    }

    /**
     * Verifica se il file è marcato come DM-only (#dm).
     */
    public static function isDmOnly(string $content): bool
    {
        return (bool) preg_match('/(?<=^|\s)#dm(?=\s|$)/i', $content);
    }

    /**
     * Punto unico di verità: la nota (nel suo complesso, tag a livello nota)
     * è visibile all'utente indicato? Verifica appartenenza alla campagna, #dm e gruppi di accesso.
     *
     * @param User|null $user Se null, usa Auth::user() corrente.
     * @param Campaign|string|null $campaign Se specificata, controlla anche l'accesso alla campagna per utenti loggati.
     */
    public static function noteIsVisibleTo(string $content, ?User $user = null, Campaign|string|null $campaign = null): bool
    {
        $user = $user ?? Auth::user();

        // Master vede sempre tutto, bypass immediato
        if ($user && $user->isMaster()) {
            return true;
        }

        // Controllo accesso alla campagna per utenti loggati
        if ($campaign !== null && $user !== null) {
            if (!$user->hasAccessToCampaign($campaign)) {
                return false;
            }
        }

        // #dm resta gestito come "gruppo implicito master": nessun altro può vederlo
        if (self::isDmOnly($content)) {
            return false;
        }

        $requiredGroups = self::requiredGroupsFromNoteTag($content);
        if (empty($requiredGroups)) {
            // Nessun tag di gruppo: la nota è pubblica
            return true;
        }

        if (!$user) {
            return false;
        }

        return $user->hasAccessToAnyGroup($requiredGroups);
    }

    /**
     * Punto unico di verità per i PDF: usa .normalize/pdf-map.json (generato da normalize.sh)
     * al posto del contenuto della nota, riusando la stessa sintassi di tag (#dm / #access-gruppo1_gruppo2)
     * e la stessa logica di noteIsVisibleTo(). Un pdf assente dalla mappa è considerato pubblico.
     *
     * @param string $normalizedPdfPath Percorso normalizzato relativo alla root del vault, con estensione .pdf
     * @param User|null $user Se null, usa Auth::user() corrente.
     * @param Campaign|string|null $campaign Se specificata, controlla anche l'accesso alla campagna per utenti loggati.
     */
    public static function pdfIsVisibleTo(string $normalizedPdfPath, ?User $user = null, Campaign|string|null $campaign = null): bool
    {
        $user = $user ?? Auth::user();

        // Master vede sempre tutto, bypass immediato
        if ($user && $user->isMaster()) {
            return true;
        }

        // Controllo accesso alla campagna per utenti loggati
        if ($campaign !== null && $user !== null) {
            if (!$user->hasAccessToCampaign($campaign)) {
                return false;
            }
        }

        $tag = VaultHelper::getPdfAccessTag($normalizedPdfPath, $campaign);

        // #dm resta gestito come "gruppo implicito master": nessun altro può vederlo
        if (self::isDmOnly($tag)) {
            return false;
        }

        $requiredGroups = self::requiredGroupsFromNoteTag($tag);
        if (empty($requiredGroups)) {
            // Nessun tag di gruppo (o entry assente dalla mappa): il pdf è pubblico
            return true;
        }

        if (!$user) {
            return false;
        }

        return $user->hasAccessToAnyGroup($requiredGroups);
    }

    /**
     * Rimuove i blocchi #startAccess-...#endAccess a cui l'utente non ha accesso.
     * Per i blocchi a cui ha accesso, rimuove i soli marcatori mantenendo il contenuto interno.
     */
    public static function filterAccessBlocks(string $content, ?User $user = null): string
    {
        $user = $user ?? Auth::user();
        $isMaster = $user && $user->isMaster();

        return preg_replace_callback(
            '/#startAccess-([a-z0-9]+(?:_[a-z0-9]+)*)\s*(.*?)\s*#endAccess/is',
            function ($m) use ($user, $isMaster) {
                if ($isMaster) {
                    return $m[2];
                }

                $requiredGroups = array_map('strtolower', explode('_', $m[1]));
                if ($user && $user->hasAccessToAnyGroup($requiredGroups)) {
                    return $m[2];
                }

                return '';
            },
            $content
        );
    }

    /**
     * Rimuove il tag a livello nota #access-gruppo1_gruppo2 dal testo.
     */
    public static function stripAccessTags(string $content): string
    {
        return preg_replace('/(?<=^|\s)#access-[a-z0-9]+(?:_[a-z0-9]+)*(?=\s|$)/i', '', $content);
    }

    /**
     * Risolve il colore del bordo per un blocco con uno o più gruppi taggati.
     * - Se tutti appartengono allo stesso ramo gerarchico: colore del gruppo più specifico (max depth).
     * - Se da rami non imparentati: colore del gruppo con ID più basso tra quelli a max depth.
     */
    public static function resolveBlockColor(array $taggedSlugs, ?int $campaignId = null): string
    {
        if (empty($taggedSlugs)) {
            return '#6c757d';
        }

        $allGroups = self::getAllGroups($campaignId);
        $taggedSlugsLower = array_map('strtolower', $taggedSlugs);
        $groups = $allGroups->filter(fn($g) => in_array(strtolower($g->slug), $taggedSlugsLower, true));

        if ($groups->isEmpty()) {
            return '#6c757d';
        }

        $maxDepth = -1;
        foreach ($groups as $group) {
            $depth = $group->depth();
            if ($depth > $maxDepth) {
                $maxDepth = $depth;
            }
        }

        $maxDepthGroups = $groups->filter(fn($g) => $g->depth() === $maxDepth);

        // Verifica se tutti i gruppi appartengono allo stesso ramo
        $allSameBranch = true;
        $ancestorMaps = [];
        foreach ($groups as $g) {
            $ancestorMaps[$g->slug] = $g->ancestorSlugs();
        }

        $groupList = $groups->values();
        $count = count($groupList);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $s1 = $groupList[$i]->slug;
                $s2 = $groupList[$j]->slug;
                $related = in_array($s1, $ancestorMaps[$s2] ?? [], true) || in_array($s2, $ancestorMaps[$s1] ?? [], true);
                if (!$related) {
                    $allSameBranch = false;
                    break 2;
                }
            }
        }

        if ($allSameBranch) {
            $chosen = $maxDepthGroups->first();
        } else {
            $chosen = $maxDepthGroups->sortBy('id')->first();
        }

        return ($chosen && $chosen->color) ? $chosen->color : '#6c757d';
    }

    /**
     * Calcola i badge da mostrare per l'utente su un blocco con gruppi richiesti.
     * Mostra il/i gruppo/i propri dell'utente più specifico/i tramite cui ha ottenuto l'accesso.
     * Per il Master mostra i gruppi taggati sul blocco.
     */
    public static function computeBadgeGroups(?User $user, array $requiredSlugs, ?int $campaignId = null): array
    {
        $user = $user ?? Auth::user();
        if (!$user) {
            return [];
        }

        $allGroups = self::getAllGroups($campaignId);
        $requiredSlugsLower = array_map('strtolower', $requiredSlugs);

        // Se Master, mostra i badge corrispondenti ai gruppi taggati
        if ($user->isMaster()) {
            return $allGroups->filter(fn($g) => in_array(strtolower($g->slug), $requiredSlugsLower, true))
                ->map(fn($g) => [
                    'name' => $g->name,
                    'slug' => $g->slug,
                    'color' => $g->color ?: '#6c757d',
                ])
                ->values()
                ->all();
        }

        $result = [];
        $seenSlugs = [];

        // Per ogni gruppo diretto assegnato all'utente
        foreach ($user->accessGroups as $group) {
            $chain = array_map('strtolower', $group->ancestorSlugs());
            if (count(array_intersect($chain, $requiredSlugsLower)) > 0) {
                if (!isset($seenSlugs[$group->slug])) {
                    $seenSlugs[$group->slug] = true;
                    $result[] = [
                        'name' => $group->name,
                        'slug' => $group->slug,
                        'color' => $group->color ?: '#6c757d',
                    ];
                }
            }
        }

        return $result;
    }
}