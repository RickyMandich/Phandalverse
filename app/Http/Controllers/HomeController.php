<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $user = Auth::user();
        $accessibleCampaigns = $user ? $user->accessibleCampaigns() : collect();

        return view('auth.dashboard', compact('accessibleCampaigns'));
    }

    /**
     * Update the user's profile information.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validateWithBag('profile', [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'showEmbedLink' => 'boolean',
            'collapseEmbed' => 'boolean',
            'default_campaign_id' => 'nullable|exists:campaigns,id',
        ]);

        if ($request->filled('default_campaign_id') && !$user->hasAccessToCampaign($request->default_campaign_id)) {
            return redirect()->route('dashboard')->withErrors([
                'default_campaign_id' => 'Non hai accesso alla campagna selezionata come predefinita.'
            ], 'profile');
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'showEmbedLink' => $request->has('showEmbedLink'),
            'collapseEmbed' => $request->has('collapseEmbed'),
            'default_campaign_id' => $validated['default_campaign_id'] ?? null,
        ]);

        return redirect()->route('dashboard')->with('success', 'Profilo aggiornato con successo');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validateWithBag('password', [
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return redirect()->route('dashboard')
                ->withErrors(['current_password' => 'La password attuale non è corretta'], 'password');
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        try {
            $hasToken = env('TELEGRAM_BOT_TOKEN') ? true : false;
            $hasChatId = env('TELEGRAM_ADMIN_CHAT_ID') ? true : false;
            $actor = Auth::user();
            $by = $actor ? ($actor->email ?? $actor->name) : 'sistema';

            if ($hasToken && $hasChatId) {
                \App\Services\TelegramService::notify(
                    'Password modificata (profilo)',
                    "Utente: {$user->email}\nModificata da: {$by}"
                );
            }
        } catch (\Exception $ex) {
            \Illuminate\Support\Facades\Log::error('Impossibile inviare notifica Telegram per password aggiornata (profilo): ' . $ex->getMessage());
        }

        return redirect()->route('dashboard')->with('success', 'Password aggiornata con successo');
    }

    /**
     * Request access to master utilities.
     */
    public function requestMasterUtils()
    {
        $user = Auth::user();

        if ($user->isMasterUtils()) {
            return redirect()->route('dashboard')->with('error', 'Hai già accesso agli strumenti da Master.');
        }

        if ($user->master_request) {
            return redirect()->route('dashboard')->with('error', 'Hai già una richiesta in sospeso.');
        }

        $user->update(['master_request' => true]);

        try {
            $admins = \App\Models\User::getAdmins();
            foreach ($admins as $admin) {
                \App\Jobs\SendQueuedEmail::dispatch(new \App\Mail\MasterRequestNotification($user), $admin->email);
            }

            if (env('TELEGRAM_BOT_TOKEN') && env('TELEGRAM_ADMIN_CHAT_ID')) {
                \App\Services\TelegramService::notify(
                    'Richiesta Strumenti Master',
                    "L'utente {$user->name} ({$user->email}) ha richiesto l'accesso agli strumenti da Master."
                );
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Errore durante l\'invio della notifica richiesta master: ' . $e->getMessage());
        }

        return redirect()->route('dashboard')->with('success', 'Richiesta inviata con successo. Un amministratore la valuterà a breve.');
    }
}
