<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DmCharacter;
use App\Models\DmSession;
use Illuminate\Support\Facades\Auth;
use App\Services\MarkdownPreprocessor;

class DmController extends Controller
{
    protected $markdown;

    public function __construct(MarkdownPreprocessor $markdown)
    {
        $this->markdown = $markdown;
    }

    public function index()
    {
        return view('dm.screen');
    }

    // API Methods for AJAX calls

    public function getCharacters()
    {
        $characters = DmCharacter::where('user_id', Auth::id())
            ->orderBy('name')
            ->get();
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
        if ($character->user_id !== Auth::id()) {
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
        if ($character->user_id !== Auth::id()) {
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
        ]);

        $session = DmSession::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'data' => $validated['data'] ?? [],
        ]);

        return response()->json($session);
    }

    public function loadSession(DmSession $session)
    {
        if ($session->user_id !== Auth::id()) {
            abort(403);
        }
        return response()->json($session);
    }

    public function updateSession(Request $request, DmSession $session)
    {
        if ($session->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'data' => 'required|array',
        ]);

        $session->update($validated);

        return response()->json(['success' => true]);
    }

    public function destroySession(DmSession $session)
    {
        if ($session->user_id !== Auth::id()) {
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

        return response()->json($session);
    }

    public function legacyLoadSession()
    {
        $session = DmSession::where('user_id', Auth::id())->orderBy('updated_at', 'desc')->first();
        return response()->json($session);
    }

    public function renderStatBlock(Request $request)
    {
        $content = $request->input('content');
        // Use static toHtml method from MarkdownPreprocessor
        return response()->json(['html' => MarkdownPreprocessor::toHtml($content, 'DM Screen')]);
    }
}
