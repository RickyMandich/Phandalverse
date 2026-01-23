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
        return view('auth.dashboard');
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
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'showEmbedLink' => $request->has('showEmbedLink'),
            'collapseEmbed' => $request->has('collapseEmbed'),
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

        // Verifica password attuale
        if (!Hash::check($validated['current_password'], $user->password)) {
            return redirect()->route('dashboard')
                ->withErrors(['current_password' => 'La password attuale non è corretta'], 'password');
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Notifica Telegram della modifica password (profilo utente)
        try {
            $hasToken = env('TELEGRAM_BOT_TOKEN') ? true : false;
            $hasChatId = env('TELEGRAM_ADMIN_CHAT_ID') ? true : false;
            $actor = Auth::user();
            $by = $actor ? ($actor->email ?? $actor->name) : 'sistema';

            \Illuminate\Support\Facades\Log::info('Telegram notify attempt for profile password change', [
                'has_token' => $hasToken,
                'has_chat_id' => $hasChatId,
                'target_user' => $user->email,
                'actor' => $by,
            ]);

            if ($hasToken && $hasChatId) {
                \App\Services\TelegramService::notify(
                    'Password modificata (profilo)',
                    "Utente: {$user->email}\nModificata da: {$by}"
                );

                \Illuminate\Support\Facades\Log::info('Telegram notify invoked for profile password change', ['target_user' => $user->email]);
            } else {
                \Illuminate\Support\Facades\Log::warning('Telegram not configured for profile password change notification', ['has_token' => $hasToken, 'has_chat_id' => $hasChatId]);
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

        // Invia notifica agli admin
        try {
            $admins = \App\Models\User::getAdmins();
            foreach ($admins as $admin) {
                \App\Jobs\SendQueuedEmail::dispatch(new \App\Mail\MasterRequestNotification($user), $admin->email);
            }

            // Notifica Telegram diretta (opzionale, ma coerente con il resto dell'app)
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
