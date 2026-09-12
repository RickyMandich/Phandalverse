<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\TelegramLinkToken;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TelegramLinkController extends Controller
{
    /**
     * Conferma un token di collegamento (personale o di gruppo).
     */
    public function confirm(string $token)
    {
        $linkToken = TelegramLinkToken::where('token', $token)->first();

        if (!$linkToken || !$linkToken->isValid()) {
            return view('telegram.link-invalid');
        }

        if ($linkToken->type === 'group') {
            if (!Auth::user()->isMaster()) {
                return view('telegram.link-not-master');
            }

            $currentlyLinked = Campaign::findByTelegramGroup($linkToken->chat_id, $linkToken->thread_id);
            $campaigns = Campaign::orderBy('order')->get();

            return view('telegram.link-group-select', compact('linkToken', 'campaigns', 'currentlyLinked'));
        }

        // type === 'personal'
        $existing = User::findByTelegramUserId($linkToken->telegram_user_id);
        if ($existing && $existing->id !== Auth::id()) {
            return view('telegram.link-already-used');
        }

        $user = Auth::user();
        $user->telegram_user_id = $linkToken->telegram_user_id;
        $user->telegram_username = $linkToken->telegram_username;
        $user->save();

        $linkToken->update(['active' => false]);

        TelegramService::sendToChat($linkToken->chat_id, "✅ Account collegato con successo a <b>" . htmlspecialchars($user->name) . "</b>!", 'HTML');

        return view('telegram.link-success', ['user' => $user]);
    }

    /**
     * Completa il collegamento di un gruppo a una campagna, scelta dal Master.
     */
    public function storeGroupLink(Request $request, string $token)
    {
        $linkToken = TelegramLinkToken::where('token', $token)->where('type', 'group')->first();

        if (!$linkToken || !$linkToken->isValid()) {
            return view('telegram.link-invalid');
        }

        if (!Auth::user()->isMaster()) {
            return view('telegram.link-not-master');
        }

        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
        ]);

        $campaign = Campaign::find($validated['campaign_id']);

        // Se la campagna scelta è già collegata a un gruppo DIVERSO, blocco l'operazione.
        if ($campaign->telegram_chat_id && ($campaign->telegram_chat_id !== $linkToken->chat_id || $campaign->telegram_thread_id !== $linkToken->thread_id)) {
            return view('telegram.link-group-already-linked', compact('campaign'));
        }

        // Un gruppo può essere collegato a una sola campagna: libero l'eventuale precedente collegamento dello stesso gruppo.
        Campaign::where('telegram_chat_id', $linkToken->chat_id)
            ->where('telegram_thread_id', $linkToken->thread_id)
            ->where('id', '!=', $campaign->id)
            ->update(['telegram_chat_id' => null, 'telegram_thread_id' => null]);

        $campaign->update([
            'telegram_chat_id' => $linkToken->chat_id,
            'telegram_thread_id' => $linkToken->thread_id,
        ]);

        $linkToken->update(['active' => false]);

        TelegramService::sendToChat(
            $linkToken->chat_id,
            "✅ Questo gruppo è stato collegato dal Master <b>" . htmlspecialchars(Auth::user()->name) . "</b> alla campagna <b>{$campaign->display_name}</b>. Potete iscrivervi con /subscribe.",
            'HTML', null, $linkToken->thread_id
        );

        return view('telegram.link-group-success', compact('campaign'));
    }
}
