<?php

namespace App\Http\Controllers;

use App\Models\SystemError;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

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

        $status = $request->status;

        // Filter by status if provided
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $errors = $query->paginate(20);

        return view('admin.errors.index', compact('errors', 'status'));
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

    // ========== GESTIONE UTENTI ==========

    /**
     * Display list of all users
     */
    public function users(Request $request)
    {
        $query = User::query()->orderBy('created_at', 'desc');

        // Ricerca per nome o email
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    /**
     * Show form to create a new user
     */
    public function createUser()
    {
        return view('admin.users.create');
    }

    /**
     * Store a new user
     */
    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'admin' => 'boolean',
            'master' => 'boolean',
            'showEmbedLink' => 'boolean',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'admin' => $request->has('admin'),
            'master' => $request->has('master'),
            'showEmbedLink' => $request->has('showEmbedLink'),
        ]);

        return redirect()->route('admin.users')->with('success', 'Utente creato con successo');
    }

    /**
     * Show form to edit an existing user
     */
    public function editUser(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update an existing user's profile data
     */
    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validateWithBag('profile', [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'admin' => 'boolean',
            'master' => 'boolean',
            'showEmbedLink' => 'boolean',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'admin' => $request->has('admin'),
            'master' => $request->has('master'),
            'showEmbedLink' => $request->has('showEmbedLink'),
        ]);

        return redirect()->route('admin.users.edit', $user)->with('success', 'Dati utente aggiornati con successo');
    }

    /**
     * Update an existing user's password
     */
    public function updateUserPassword(Request $request, User $user)
    {
        $validated = $request->validateWithBag('password', [
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('admin.users.edit', $user)->with('success', 'Password aggiornata con successo');
    }

    /**
     * Delete a user
     */
    public function deleteUser(User $user)
    {
        // Non permettere di eliminare se stessi
        if ($user->id === Auth::id()) {
            return redirect()->route('admin.users')->with('error', 'Non puoi eliminare te stesso');
        }

        $user->delete();

        return redirect()->route('admin.users')->with('success', 'Utente eliminato con successo');
    }
}

