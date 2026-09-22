<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DmCharacter;
use App\Models\DmSession;
use Illuminate\Support\Facades\Auth;
use App\Services\MarkdownPreprocessor;
use App\Services\StatBlockParser;
use App\Services\CustomLogger;

class DmController extends Controller
{
    protected $markdown;

    public function __construct(MarkdownPreprocessor $markdown)
    {
        $this->markdown = $markdown;
    }

    public function index()
    {
        CustomLogger::screen("view-master", "DM Screen loaded by ID: " . Auth::id());
        return view('dm.screen');
    }

    public function manage()
    {
        return view('dm.manage');
    }

    public function getManagementData()
    {
        $userId = Auth::id();
        $user = Auth::user();
        $isMaster = $user->isMaster();
        $isAdmin = $user->isAdmin();

        $characters = DmCharacter::with('user')
            ->when(!$isAdmin, function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhere('type', 'template');
            })
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $sessions = DmSession::when(!$isAdmin, function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->orderBy('updated_at', 'desc')
            ->get();

        if (!$user->isMasterUtils()) {
            $characters->each(function ($char) {
                if ($char->stats && isset($char->stats['notes'])) {
                    $stats = $char->stats;
                    $stats['notes'] = '[ACCESSO LIMITATO]';
                    $char->stats = $stats;
                }
            });
        }

        // Only full Master sees personalNotes in session combatants
        if (!$isMaster) {
            $sessions->each(function ($session) use ($user, $isMaster) {
                if ($session->data && isset($session->data['combatants'])) {
                    $data = $session->data;
                    foreach ($data['combatants'] as &$c) {
                        unset($c['personalNotes']);
                        if (!$user->isMasterUtils() && isset($c['stats']['notes'])) {
                            $c['stats']['notes'] = '[ACCESSO LIMITATO]';
                        }
                    }
                    $session->data = $data;
                }
            });
        }

        return response()->json([
            'characters' => $characters,
            'sessions' => $sessions
        ]);
    }

    // API Methods for AJAX calls

    public function getCharacters()
    {
        $userId = Auth::id();
        $isMaster = Auth::user()->isMaster();

        // Public templates + User's own players/groups
        $characters = DmCharacter::with('user')
            ->where('type', 'template')
            ->orWhere('user_id', $userId)
            ->orderBy('name')
            ->get();

        if (!Auth::user()->isMasterUtils()) {
            $characters->each(function ($char) {
                if ($char->stats && isset($char->stats['notes'])) {
                    $stats = $char->stats;
                    $stats['notes'] = '[ACCESSO LIMITATO]';
                    $char->stats = $stats;
                }
            });
        }

        return response()->json($characters);
    }

    public function storeCharacter(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:player,template,group',
            'stats' => 'nullable|array',
        ]);

        $character = DmCharacter::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'type' => $validated['type'],
            'stats' => $validated['stats'],
        ]);

        return response()->json($character);
    }

    public function updateCharacter(Request $request, DmCharacter $character)
    {
        if ($character->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:player,template,group',
            'stats' => 'nullable|array',
        ]);

        $character->update($validated);

        return response()->json($character);
    }

    public function destroyCharacter(DmCharacter $character)
    {
        if ($character->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            abort(403);
        }

        $character->delete();

        return response()->json(['success' => true]);
    }

    public function getSessions()
    {
        $sessions = DmSession::where('user_id', Auth::id())
            ->orderBy('updated_at', 'desc')
            ->get();
        return response()->json($sessions);
    }

    public function storeSession(Request $request)
    {
        CustomLogger::screen("session-create", "Attempting to store session. Name: " . ($request->input('name') ?: 'NULL'));
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'data' => 'nullable|array',
                'system' => 'nullable|string|max:50',
            ]);

            $session = DmSession::create([
                'user_id' => Auth::id(),
                'name' => $validated['name'],
                'data' => $validated['data'] ?? [],
                'system' => $validated['system'] ?? 'dnd5e',
            ]);

            CustomLogger::screen("session-create", "SUCCESS: New session created: {$session->name} (ID: {$session->id}, System: {$session->system})");

            return response()->json($session);
        } catch (\Illuminate\Validation\ValidationException $e) {
            CustomLogger::screen("session-create", "VALIDATION ERROR: " . json_encode($e->errors()));
            return response()->json(['error' => 'Validation failed', 'details' => $e->errors()], 422);
        } catch (\Exception $e) {
            CustomLogger::screen("session-create", "GENERAL ERROR: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function loadSession(DmSession $session)
    {
        if ($session->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            abort(403);
        }
        CustomLogger::screen("{$session->share_code}-master", "Master loading specific session ID: {$session->id} (System: {$session->system})");
        return response()->json($session);
    }

    public function updateSession(Request $request, DmSession $session)
    {
        if ($session->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'data' => 'required|array',
            'system' => 'sometimes|string|max:50',
        ]);

        $session->update($validated);

        CustomLogger::screen("{$session->share_code}-master", "Session ID {$session->id} updated. System: {$session->system}.");

        return response()->json(['success' => true]);
    }

    public function destroySession(DmSession $session)
    {
        if ($session->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            abort(403);
        }

        $session->delete();

        return response()->json(['success' => true]);
    }

    public function saveSession(Request $request)
    {
        // Keep this for legacy or simple auto-save without ID if needed, 
        // but preferred to use updateSession now.
        $validated = $request->validate([
            'data' => 'required|array',
        ]);

        $session = DmSession::updateOrCreate(
            ['user_id' => Auth::id(), 'name' => 'Default Session'],
            ['data' => $validated['data']]
        );

        CustomLogger::screen("{$session->share_code}-master", "Legacy Save (Default). Data combatants: " . count($validated['data']['combatants'] ?? []) . ", Round: " . ($validated['data']['round'] ?? '1'));

        return response()->json($session);
    }

    public function legacyLoadSession()
    {
        $session = DmSession::where('user_id', Auth::id())->orderBy('updated_at', 'desc')->first();
        if ($session && !$session->share_code) {
            $session->share_code = DmSession::generateUniqueCode();
            $session->save();
        }

        $code = $session ? $session->share_code : 'none';
        CustomLogger::screen("{$code}-master", "Legacy Load Session: " . ($session ? "ID {$session->id}" : "No session found"));

        return response()->json($session);
    }

    public function renderStatBlock(Request $request)
    {
        if (!Auth::user()->isMasterUtils()) {
            return response()->json(['html' => '<div class="alert alert-warning small">Accesso limitato: solo i Master possono vedere i dettagli dello Stat Block.</div>']);
        }

        $content = $request->input('content');
        // Use static toHtml method from MarkdownPreprocessor
        return response()->json(['html' => MarkdownPreprocessor::toHtml($content, 'DM Screen')]);
    }

    /**
     * Scansiona Vault/materiale/manuali/stat-block/*.md (on-demand, nessuna cache: il master
     * la apre quando vuole importare, va bene anche se un po' lenta con molti file) e ritorna
     * l'elenco delle stat-block trovate con il nome che avrebbero da importate, segnalando
     * per ciascuna se esiste già un DmCharacter (type=template) con lo stesso nome.
     */
    public function scanMaterialeStatBlocks()
    {
        $dir = base_path('Vault/materiale/manuali/stat-block');
        $results = [];

        if (is_dir($dir)) {
            $files = glob($dir . DIRECTORY_SEPARATOR . '*.md') ?: [];
            sort($files);

            $existingNames = DmCharacter::where('type', 'template')
                ->pluck('name')
                ->map(fn($n) => strtolower($n))
                ->all();

            foreach ($files as $file) {
                try {
                    $markdown = file_get_contents($file);
                    $parsed = StatBlockParser::parse($markdown);
                    $name = $parsed['name'] ?: StatBlockParser::fallbackNameFromFilename($file);

                    $results[] = [
                        'path' => basename($file),
                        'name' => $name,
                        'subtitle' => $parsed['subtitle'],
                        'ac' => $parsed['ac'],
                        'hp_formula' => $parsed['hp_formula'],
                        'conflict' => in_array(strtolower($name), $existingNames, true),
                    ];
                } catch (\Throwable $e) {
                    $results[] = [
                        'path' => basename($file),
                        'name' => StatBlockParser::fallbackNameFromFilename($file),
                        'error' => 'Impossibile leggere/interpretare il file: ' . $e->getMessage(),
                        'conflict' => false,
                    ];
                }
            }
        }

        return response()->json(['stat_blocks' => $results]);
    }

    /**
     * Importa in batch le stat-block selezionate. Ogni nota è solo la fonte iniziale: una volta
     * importato, il DmCharacter creato/aggiornato non resta collegato al file (nessun campo che
     * lo referenzi), così un rename/spostamento della nota non rompe nulla lato Fight Manager.
     */
    public function importMaterialeStatBlocks(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.path' => 'required|string',
            'items.*.resolution' => 'required|in:skip,overwrite,duplicate',
        ]);

        $dir = base_path('Vault/materiale/manuali/stat-block');
        $imported = [];
        $skipped = [];
        $errors = [];

        foreach ($validated['items'] as $item) {
            if ($item['resolution'] === 'skip') {
                $skipped[] = $item['path'];
                continue;
            }

            // Nessuna traversal fuori dalla cartella stat-block: solo il nome file, non un path.
            $safePath = basename($item['path']);
            $fullPath = $dir . DIRECTORY_SEPARATOR . $safePath;

            if (!is_file($fullPath)) {
                $errors[] = ['path' => $item['path'], 'error' => 'File non trovato'];
                continue;
            }

            try {
                $parsed = StatBlockParser::parse(file_get_contents($fullPath));
                $name = $parsed['name'] ?: StatBlockParser::fallbackNameFromFilename($fullPath);

                $stats = [
                    'ac' => $parsed['ac'],
                    'hp_formula' => $parsed['hp_formula'],
                    'speed' => $parsed['speed'],
                    'subtitle' => $parsed['subtitle'],
                    'attributes' => $parsed['attributes'],
                    'saving_throws' => null,
                    'notes' => $parsed['notes'],
                ];

                $existing = DmCharacter::where('type', 'template')
                    ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                    ->first();

                if ($existing && $item['resolution'] === 'overwrite') {
                    $existing->update(['stats' => $stats]);
                    $imported[] = ['path' => $item['path'], 'name' => $existing->name, 'action' => 'overwritten', 'id' => $existing->id];
                    continue;
                }

                $finalName = $name;
                if ($existing && $item['resolution'] === 'duplicate') {
                    $finalName = $this->nextAvailableCharacterName($name);
                }

                $character = DmCharacter::create([
                    'user_id' => Auth::id(),
                    'name' => $finalName,
                    'type' => 'template',
                    'stats' => $stats,
                ]);

                $imported[] = ['path' => $item['path'], 'name' => $character->name, 'action' => 'created', 'id' => $character->id];
            } catch (\Throwable $e) {
                $errors[] = ['path' => $item['path'], 'error' => $e->getMessage()];
            }
        }

        return response()->json(['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors]);
    }

    /**
     * Prossimo nome libero in stile Esplora File: "Nome", "Nome (2)", "Nome (3)", ...
     */
    protected function nextAvailableCharacterName(string $baseName): string
    {
        $existingNames = DmCharacter::where('type', 'template')
            ->where(function ($q) use ($baseName) {
                $q->whereRaw('LOWER(name) = ?', [strtolower($baseName)])
                    ->orWhereRaw('LOWER(name) LIKE ?', [strtolower($baseName) . ' (%']);
            })
            ->pluck('name')
            ->map(fn($n) => strtolower($n))
            ->all();

        $n = 2;
        while (in_array(strtolower($baseName) . ' (' . $n . ')', $existingNames, true)) {
            $n++;
        }

        return $baseName . ' (' . $n . ')';
    }

    public function playerIndex()
    {
        return view('dm.player_index');
    }

    public function playerView($share_code)
    {
        try {
            $session = DmSession::where('share_code', $share_code)->firstOrFail();

            if ($session->system === 'powerfail') {
                return view('dm.player_powerfail', compact('session'));
            }

            return view('dm.player', compact('session'));
        } catch (\Exception $e) {
            return redirect()->route('dm.player.index')->with('error', 'Sessione non trovata');
        }
    }

    public function publicLoadSession($share_code)
    {
        $session = DmSession::where('share_code', $share_code)->firstOrFail();

        CustomLogger::screen("{$share_code}-player", "Public Load Session requested. Combatants in DB: " . count($session->data['combatants'] ?? []) . ", Round: " . ($session->data['round'] ?? '1'));

        return response()->json($session);
    }

    public function powerfailScreen()
    {
        CustomLogger::screen("view-powerfail", "Powerfail Master Screen loaded by ID: " . Auth::id());
        return view('dm.powerfail_screen');
    }
}
