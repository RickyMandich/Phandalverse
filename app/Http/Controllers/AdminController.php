<?php

namespace App\Http\Controllers;

use App\Models\SystemError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    /**
     * Check if user is admin, return 403 view if not
     */
    private function checkAdmin()
    {
        if (!Auth::admin()) {
            return view('errors.403');
        }
        return null;
    }

    /**
     * Display list of system errors
     */
    public function errors(Request $request)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $query = SystemError::query()->orderBy('created_at', 'desc');

        // Filter by status if provided
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $errors = $query->paginate(20);

        return view('admin.errors.index', compact('errors'));
    }

    /**
     * Display single error details
     */
    public function showError(SystemError $error)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        return view('admin.errors.show', compact('error'));
    }

    /**
     * Update error status and notes
     */
    public function updateError(Request $request, SystemError $error)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $validated = $request->validate([
            'status' => 'required|in:new,in_progress,resolved,ignored',
            'admin_notes' => 'nullable|string',
        ]);

        $error->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? $error->admin_notes,
            'resolved_by' => in_array($validated['status'], ['resolved', 'ignored']) ? Auth::id() : null,
            'resolved_at' => in_array($validated['status'], ['resolved', 'ignored']) ? now() : null,
        ]);

        return redirect()->route('admin.errors.show', $error)->with('success', 'Stato errore aggiornato');
    }

    /**
     * Quick action from email link
     */
    public function quickActionError(SystemError $error, string $action)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        if (!in_array($action, ['resolved', 'ignored', 'in_progress'])) {
            return redirect()->route('admin.errors')->with('error', 'Azione non valida');
        }

        $error->update([
            'status' => $action,
            'resolved_by' => in_array($action, ['resolved', 'ignored']) ? Auth::id() : null,
            'resolved_at' => in_array($action, ['resolved', 'ignored']) ? now() : null,
        ]);

        return redirect()->route('admin.errors.show', $error)->with('success', 'Errore segnato come ' . $action);
    }
}

