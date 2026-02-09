<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DmCharacter;
use App\Models\DmSession;
use Illuminate\Support\Facades\Auth;
use App\Services\MarkdownPreprocessor;
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

        return response()->json($session);
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
