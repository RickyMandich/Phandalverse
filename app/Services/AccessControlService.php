<?php

namespace App\Services;

use App\Models\AccessGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class AccessControlService
{
    private static ?Collection $groupsCache = null;

    /**
     * Cache dei gruppi caricati per la request corrente.
     */
    public static function getAllGroups(): Collection
    {
        if (self::$groupsCache === null) {
            try {
                self::$groupsCache = AccessGroup::with('parent')->get();
            } catch (\Throwable $e) {
                self::$groupsCache = new Collection();
            }
        }
        return self::$groupsCache;
    }

    public static function clearCache(): void
    {
        self::$groupsCache = null;
    }

    /**
     * Estrae gli slug dei gruppi richiesti da un tag #access:gruppo1|gruppo2
     * (nota intera). Ritorna array vuoto se il tag non è presente.
     */
    public static function requiredGroupsFromNoteTag(string $content): array
    {
        if (!preg_match('/(?<=^|\s)#access:([a-z0-9\-]+(?:\|[a-z0-9\-]+)*)(?=\s|$)/i', $content, $m)) {
            return [];
        }

        return array_map('strtolower', explode('|', $m[1]));
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
     * è visibile all'utente indicato? Usato ovunque al posto del vecchio
     * check duplicato su #dm + Auth::isMaster().
     *
     * @param User|null $user Se null, usa Auth::user() corrente.
     */
    public static function noteIsVisibleTo(string $content, ?User $user = null): bool
    {
        $user = $user ?? Auth::user();

        // Master vede sempre tutto, bypass immediato
        if ($user && $user->isMaster()) {
            return true;
        }

        // #dm resta gestito come "gruppo implicito master": nessun altro può vederlo
        if (self::isDmOnly($content)) {
            return false;
        }

        $requiredGroups = self::requiredGroupsFromNoteTag($content);
        if (empty($requiredGroups)) {
            // Nessun tag di gruppo: la nota è pubblica (comportamento invariato di oggi)
            return true;
        }

        if (!$user) {
            return false;
        }

        return $user->hasAccessToAnyGroup($requiredGroups);
    }

    /**
     * Rimuove i blocchi #startAccess...#endAccess a cui l'utente non ha accesso.
     * Per i blocchi a cui ha accesso, rimuove i soli marcatori mantenendo il contenuto interno.
     */
    public static function filterAccessBlocks(string $content, ?User $user = null): string
    {
        $user = $user ?? Auth::user();
        $isMaster = $user && $user->isMaster();

        return preg_replace_callback(
            '/#startAccess:([a-z0-9\-]+(?:\|[a-z0-9\-]+)*)\s*(.*?)\s*#endAccess/is',
            function ($m) use ($user, $isMaster) {
                if ($isMaster) {
                    return $m[2];
                }

                $requiredGroups = array_map('strtolower', explode('|', $m[1]));
                if ($user && $user->hasAccessToAnyGroup($requiredGroups)) {
                    return $m[2];
                }

                return '';
            },
            $content
        );
    }

    /**
     * Rimuove il tag a livello nota #access:gruppo1|gruppo2 dal testo.
     */
    public static function stripAccessTags(string $content): string
    {
        return preg_replace('/(?<=^|\s)#access:[a-z0-9\-]+(?:\|[a-z0-9\-]+)*(?=\s|$)/i', '', $content);
    }

    /**
     * Risolve il colore del bordo per un blocco con uno o più gruppi taggati.
     * - Se tutti appartengono allo stesso ramo gerarchico: colore del gruppo più specifico (max depth).
     * - Se da rami non imparentati: colore del gruppo con ID più basso tra quelli a max depth.
     */
    public static function resolveBlockColor(array $taggedSlugs): string
    {
        if (empty($taggedSlugs)) {
            return '#6c757d';
        }

        $allGroups = self::getAllGroups();
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
    public static function computeBadgeGroups(?User $user, array $requiredSlugs): array
    {
        $user = $user ?? Auth::user();
        if (!$user) {
            return [];
        }

        $allGroups = self::getAllGroups();
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
            $chain = $group->ancestorSlugs();
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