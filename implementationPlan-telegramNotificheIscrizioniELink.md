# Implementation Plan: Notifiche Telegram, Iscrizioni e Collegamento Account/Gruppi

## Obiettivo
1. Bottone "Iscriviti a tutte" su `/subscribe` (rilevante solo per le chat private, con più campagne).
2. Dashboard admin per controllare le iscrizioni Telegram; il collegamento gruppo↔campagna si gestisce dalla pagina Campagne già esistente.
3. Collegamento (autenticazione) dell'account Telegram **privato** con l'utente del sito, tramite `/link` → link con token da 1h → conferma sul sito. Aggiunto anche `/unlink`.
4. Collegamento di una **chat di gruppo** a una campagna: chi lancia `/link` nel gruppo genera un token, ma solo un **Master** può completare il collegamento sul sito, scegliendo a quale campagna agganciare quel gruppo. **Un gruppo può essere collegato a una sola campagna alla volta** (e, per come è modellato lo schema, anche una campagna ha al massimo un gruppo collegato — se in futuro servirà collegare più gruppi alla stessa campagna, lo schema andrà rivisto). L'accesso del gruppo alle notifiche non dipende più da un singolo utente, ma da questo collegamento.
5. `/subscribe` e `/unsubscribe`:
   - in chat **privata**: richiedono l'account collegato e mostrano solo le campagne a cui l'utente ha accesso (eventualmente più di una → tastiera di scelta + "iscriviti a tutte").
   - in **gruppo**: agiscono sull'unica campagna eventualmente collegata a quel gruppo/topic (nessun controllo sul singolo utente, nessuna scelta multipla necessaria dato il vincolo 1:1).
6. Disattivazione (via commento del codice, non cancellazione) dei comandi `/search` e `/view`.
7. Al momento dell'invio di una notifica broadcast per una campagna, verificare che ogni iscritto abbia ancora accesso (utente collegato per le chat private, collegamento gruppo↔campagna ancora presente per i gruppi); se l'accesso non è più verificabile, avvisare l'iscritto e cancellare la sua riga da `telegram_subscribers`.

---

## 1. Migrazioni Database

Seguendo la convenzione già in uso nel progetto, ogni migrazione riporta nel commento la query SQL equivalente per l'esecuzione manuale su produzione, anche se ora è disponibile l'accesso SSH e si può eseguire `php artisan migrate` direttamente.

### 1.1 `add_telegram_fields_to_users_table`
Aggiunge a `users`:
- `telegram_user_id` BIGINT UNSIGNED NULL UNIQUE — ID numerico Telegram (stabile, univoco) dell'utente collegato in chat privata.
- `telegram_username` VARCHAR(255) NULL — username Telegram al momento del collegamento (solo per display).

```php
Schema::table('users', function (Blueprint $table) {
    $table->unsignedBigInteger('telegram_user_id')->nullable()->unique()->after('default_campaign_id');
    $table->string('telegram_username')->nullable()->after('telegram_user_id');
});
```

### 1.2 `add_telegram_user_id_to_telegram_subscribers_table`
Aggiunge a `telegram_subscribers`:
- `telegram_user_id` BIGINT UNSIGNED NULL, con indice. Per le iscrizioni **private** rappresenta l'utente collegato che le ha attivate. Per le iscrizioni di **gruppo** resta valorizzato solo a scopo di audit (chi ha lanciato `/subscribe`), ma **non** viene usato per verificare l'accesso del gruppo (vedi sezione 7).

```php
Schema::table('telegram_subscribers', function (Blueprint $table) {
    $table->unsignedBigInteger('telegram_user_id')->nullable()->after('username')->index();
});
```

### 1.3 `create_telegram_link_tokens_table`
Tabella unica per **entrambi** i flussi di collegamento (privato e gruppo), distinti dal campo `type`. Soft-delete tramite `active` (i token non vengono mai cancellati, per debug):

```php
Schema::create('telegram_link_tokens', function (Blueprint $table) {
    $table->id();
    $table->string('token', 64)->unique();
    $table->enum('type', ['personal', 'group'])->default('personal');
    $table->unsignedBigInteger('telegram_user_id'); // personal: utente da collegare; group: chi ha lanciato /link (solo audit)
    $table->string('telegram_username')->nullable();
    $table->string('chat_id');
    $table->string('thread_id')->nullable(); // rilevante solo per type = group
    $table->boolean('active')->default(true); // false = già usato/consumato
    $table->timestamp('expires_at');
    $table->timestamps();
});
```

### 1.4 `add_telegram_group_fields_to_campaigns_table`
Aggiunge a `campaigns` le due colonne che rappresentano l'eventuale gruppo Telegram collegato:

```php
Schema::table('campaigns', function (Blueprint $table) {
    $table->string('telegram_chat_id')->nullable()->after('order');
    $table->string('telegram_thread_id')->nullable()->after('telegram_chat_id');
    $table->unique(['telegram_chat_id', 'telegram_thread_id']);
});
```

L'indice unique su `(telegram_chat_id, telegram_thread_id)` impedisce che lo stesso gruppo/topic risulti collegato a due campagne diverse contemporaneamente (i NULL multipli sono ammessi da MySQL, quindi le campagne senza gruppo non danno conflitto).

---

## 2. Modelli

### 2.1 `App\Models\User`
- Aggiungere `telegram_user_id` e `telegram_username` a `$fillable`.
```php
public function isTelegramLinked(): bool
{
    return !empty($this->telegram_user_id);
}

public static function findByTelegramUserId(int $telegramUserId): ?self
{
    return static::where('telegram_user_id', $telegramUserId)->first();
}
```

### 2.2 `App\Models\Campaign`
- Aggiungere `telegram_chat_id` e `telegram_thread_id` a `$fillable`.
```php
public function isTelegramGroupLinked(): bool
{
    return !empty($this->telegram_chat_id);
}

public static function findByTelegramGroup(string $chatId, ?string $threadId): ?self
{
    return static::where('telegram_chat_id', $chatId)->where('telegram_thread_id', $threadId)->first();
}
```

### 2.3 `App\Models\TelegramSubscriber`
- Aggiungere `telegram_user_id` a `$fillable`.

### 2.4 Nuovo `App\Models\TelegramLinkToken`
```php
class TelegramLinkToken extends Model
{
    protected $fillable = ['token', 'type', 'telegram_user_id', 'telegram_username', 'chat_id', 'thread_id', 'active', 'expires_at'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'expires_at' => 'datetime'];
    }

    public function isValid(): bool
    {
        return $this->active && $this->expires_at->isFuture();
    }

    public static function generateFor(string $type, int $telegramUserId, ?string $telegramUsername, string $chatId, ?string $threadId = null): self
    {
        return static::create([
            'token' => Str::random(48),
            'type' => $type, // 'personal' | 'group'
            'telegram_user_id' => $telegramUserId,
            'telegram_username' => $telegramUsername,
            'chat_id' => $chatId,
            'thread_id' => $threadId,
            'active' => true,
            'expires_at' => now()->addHour(),
        ]);
    }
}
```

Non serve più alcun modello per il collegamento gruppo↔campagna: l'informazione vive direttamente su `Campaign`.

---

## 3. `TelegramBotController` — modifiche

### 3.1 Estrazione dati nel webhook
In `webhook()`, oltre a `$chatId`, `$threadId`, `$text`, estrarre:
```php
$telegramUserId = $message['from']['id'] ?? null;
$isGroup = ($chat['type'] !== 'private');
```
Passarli ai metodi che ne hanno bisogno. Stesso discorso in `handleCallback()`: `$telegramUserId = $callbackQuery['from']['id'] ?? null;` e `$isGroup = str_starts_with((string) $chatId, '-');`.

### 3.2 Nuovo helper privato di autenticazione (solo chat private)
```php
protected function requireLinkedUser($chatId, ?int $telegramUserId, ?string $threadId): ?User
{
    $user = $telegramUserId ? User::findByTelegramUserId($telegramUserId) : null;
    if (!$user) {
        TelegramService::sendToChat(
            $chatId,
            "🔒 Devi prima collegare il tuo account con il sito.\nUsa il comando /link in privato per autenticarti.",
            'HTML', null, $threadId
        );
    }
    return $user;
}
```

### 3.3 `/link` — comportamento diverso per chat privata e gruppo (`handleLink`)

```php
protected function handleLink($chatId, $threadId, ?int $telegramUserId, ?string $telegramUsername, bool $isGroup)
{
    if ($isGroup) {
        $token = TelegramLinkToken::generateFor('group', $telegramUserId, $telegramUsername, $chatId, $threadId);
        $url = rtrim(config('app.url'), '/') . '/telegram/link/' . $token->token;
        $replyMarkup = ['inline_keyboard' => [[['text' => '🔗 Collega questo gruppo a una campagna', 'url' => $url]]]];
        TelegramService::sendToChat(
            $chatId,
            "Per collegare questo gruppo a una campagna, un <b>Master</b> deve aprire il link qui sotto ed effettuare l'accesso sul sito. Il link scade tra 1 ora.",
            'HTML', $replyMarkup, $threadId
        );
        return;
    }

    $token = TelegramLinkToken::generateFor('personal', $telegramUserId, $telegramUsername, $chatId);
    $url = rtrim(config('app.url'), '/') . '/telegram/link/' . $token->token;
    $replyMarkup = ['inline_keyboard' => [[['text' => '🔗 Collega il tuo account', 'url' => $url]]]];
    TelegramService::sendToChat(
        $chatId,
        "Clicca il pulsante per collegare il tuo account Telegram a quello del sito. Il link scade tra 1 ora.",
        'HTML', $replyMarkup, $threadId
    );
}
```

### 3.4 `/unlink` — comportamento diverso per chat privata e gruppo (`handleUnlink`)

**Chat privata**: invariato rispetto alla versione precedente del piano.
- Richiede utente collegato (`requireLinkedUser`).
- Chiede conferma con bottone inline (`callback_data: 'unlink:confirm'`).
- In `handleCallback()`, branch `unlink:confirm`: azzera `telegram_user_id`/`telegram_username` dell'utente.
- **Effetto a cascata**: non cancella subito le iscrizioni; la pulizia avviene in modo lazy alla prossima notifica (sezione 7).

**Gruppo** (semplificato grazie al vincolo 1:1):
```php
protected function handleUnlink($chatId, $threadId, ?int $telegramUserId, bool $isGroup)
{
    if ($isGroup) {
        $master = $telegramUserId ? User::findByTelegramUserId($telegramUserId) : null;
        if (!$master || !$master->isMaster()) {
            TelegramService::sendToChat($chatId, "Solo un Master collegato al proprio account può gestire il collegamento di questo gruppo. Usa prima /link in privato per collegare il tuo account.", 'HTML', null, $threadId);
            return;
        }

        $campaign = Campaign::findByTelegramGroup($chatId, $threadId);
        if (!$campaign) {
            TelegramService::sendToChat($chatId, "Questo gruppo non è collegato a nessuna campagna.", 'HTML', null, $threadId);
            return;
        }

        $replyMarkup = ['inline_keyboard' => [[['text' => "📴 Scollega da {$campaign->display_name}", 'callback_data' => "unlinkgroup:{$campaign->id}"]]]];
        TelegramService::sendToChat($chatId, "Questo gruppo è collegato a <b>{$campaign->display_name}</b>. Confermi lo scollegamento?", 'HTML', $replyMarkup, $threadId);
        return;
    }

    // ... comportamento privato invariato (sezione sopra)
}
```
In `handleCallback()`, nuovo branch `unlinkgroup:{campaignId}`: rivalida Master+campagna (stesso controllo), poi azzera `telegram_chat_id`/`telegram_thread_id` sulla campagna e cancella la/e riga/e corrispondente/i di `telegram_subscribers` per quel chat/thread/campagna (accesso non più garantito).

### 3.5 `/subscribe` — riscrittura `handleSubscribe`

```php
protected function handleSubscribe($chatId, $username, $threadId, ?int $telegramUserId, bool $isGroup)
{
    if ($isGroup) {
        $campaign = Campaign::findByTelegramGroup($chatId, $threadId);
        if (!$campaign) {
            TelegramService::sendToChat($chatId, "Questo gruppo non è ancora collegato a nessuna campagna. Chiedi a un Master di eseguire /link qui per collegarlo.", 'HTML', null, $threadId);
            return;
        }

        // Un solo esito possibile, come già oggi nel ramo "campagna singola":
        // iscrizione diretta o messaggio "già iscritto", nessuna tastiera di scelta necessaria.
        $subscriber = TelegramSubscriber::where('chat_id', $chatId)->where('thread_id', $threadId)->where('campaign_id', $campaign->id)->first();
        if ($subscriber) {
            TelegramService::sendToChat($chatId, "Questa chat/topic è già iscritta alle notifiche di <b>{$campaign->display_name}</b>! ✅", 'HTML', null, $threadId);
        } else {
            TelegramSubscriber::create(['campaign_id' => $campaign->id, 'chat_id' => $chatId, 'thread_id' => $threadId, 'username' => $username, 'telegram_user_id' => $telegramUserId]);
            TelegramService::sendToChat($chatId, "Iscrizione completata per <b>{$campaign->display_name}</b>! 🔔", 'HTML', null, $threadId);
        }
        return;
    }

    $user = $this->requireLinkedUser($chatId, $telegramUserId, $threadId);
    if (!$user) return;

    $campaigns = $user->accessibleCampaigns(); // già ordinate per 'order', Master vede tutte

    if ($campaigns->isEmpty()) {
        TelegramService::sendToChat($chatId, "Non hai accesso a nessuna campagna al momento.", 'HTML', null, $threadId);
        return;
    }

    // Logica di oggi (singola campagna / tastiera con più campagne), con l'aggiunta del bottone
    // "🌟 Iscriviti a TUTTE" (callback_data: sub:all) quando count() > 1.
}
```
Il bottone "Iscriviti a tutte" resta rilevante solo per il ramo privato (nei gruppi, essendoci al massimo una campagna collegata, non serve alcuna scelta).

### 3.6 Callback `sub:camp_{id}` e `sub:all` (solo ramo privato)
- Prima di creare l'iscrizione: `requireLinkedUser` + `$user->hasAccessToCampaign($campaign)`.
- `sub:all`: itera su `$user->accessibleCampaigns()` e crea le iscrizioni mancanti.
- Il ramo gruppo non usa più questi due callback (l'iscrizione di gruppo avviene direttamente da `/subscribe`, senza tastiera, vedi 3.5).

### 3.7 `/unsubscribe` — `handleUnsubscribe`

- **Chat privata**: richiede `requireLinkedUser` come primo passo; per il resto logica identica a quella attuale (tastiera se più iscrizioni).
- **Gruppo**: nessun controllo di autenticazione personale. Essendoci al massimo un'iscrizione per gruppo/topic (vincolo 1 campagna per gruppo), non serve tastiera di scelta: si cancella direttamente l'unica riga esistente, o si informa che non ci sono iscrizioni attive.

### 3.8 Callback `unsub:camp_{id}` e `unsub:all` (solo ramo privato)
Restano come nel piano precedente, con `requireLinkedUser` quando `!$isGroup`. Nei gruppi non sono più necessari, dato che `/unsubscribe` di gruppo non genera più una tastiera.

### 3.9 Disattivazione di `/search` e `/view` (via commento del codice)
```php
// Temporaneamente disattivati in attesa del refactor multi-campagna (vedi implementationPlan-telegramNotificheIscrizioniELink.md)
// } elseif ($command === '/search') {
//     $this->handleSearch($chatId, $params, $threadId);
// } elseif ($command === '/view') {
//     $this->handleView($chatId, $params, $threadId);
```
Nuovo `elseif` esplicito:
```php
} elseif (in_array($command, ['/search', '/view'])) {
    TelegramService::sendToChat($chatId, "🔧 Questo comando è temporaneamente disattivato, verrà ripristinato con il supporto completo alle campagne multiple.", 'HTML', null, $threadId);
```
I metodi `handleSearch()` e `handleView()` restano nel codice (non cancellati), così come il branch `view:` nella callback.

### 3.10 Aggiornamento `handleStart` (messaggio di help)
- **Privato**: `/link`, `/unlink`, `/subscribe`, `/unsubscribe` come nel piano precedente.
- **Gruppo**:
```
🔗 /link - Collega questo gruppo a una campagna (completato da un Master sul sito)
🔓 /unlink - Scollega questo gruppo dalla campagna (richiede Master collegato)
🚀 /subscribe - Attiva le notifiche del gruppo per la campagna collegata
📴 /unsubscribe - Disattiva le notifiche del gruppo
```
Rimossi in entrambi i casi i riferimenti a `/search` e `/view`.

---

## 4. `TelegramLinkController` (web)

```php
class TelegramLinkController extends Controller
{
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

        TelegramService::sendToChat($linkToken->chat_id, "✅ Account collegato con successo a <b>{$user->name}</b>!", 'HTML');

        return view('telegram.link-success', ['user' => $user]);
    }

    public function storeGroupLink(Request $request, string $token)
    {
        $linkToken = TelegramLinkToken::where('token', $token)->where('type', 'group')->first();

        if (!$linkToken || !$linkToken->isValid()) {
            return view('telegram.link-invalid');
        }
        if (!Auth::user()->isMaster()) {
            return view('telegram.link-not-master');
        }

        $validated = $request->validate(['campaign_id' => 'required|exists:campaigns,id']);
        $campaign = Campaign::find($validated['campaign_id']);

        // Se la campagna scelta è già collegata a un gruppo DIVERSO, blocco: va scollegata prima (da /admin/campaigns o con /unlink nell'altro gruppo)
        if ($campaign->telegram_chat_id && ($campaign->telegram_chat_id !== $linkToken->chat_id || $campaign->telegram_thread_id !== $linkToken->thread_id)) {
            return view('telegram.link-group-already-linked', compact('campaign'));
        }

        // Questo gruppo può essere collegato a una sola campagna: libero l'eventuale precedente collegamento dello stesso gruppo
        Campaign::where('telegram_chat_id', $linkToken->chat_id)
            ->where('telegram_thread_id', $linkToken->thread_id)
            ->where('id', '!=', $campaign->id)
            ->update(['telegram_chat_id' => null, 'telegram_thread_id' => null]);

        $campaign->update(['telegram_chat_id' => $linkToken->chat_id, 'telegram_thread_id' => $linkToken->thread_id]);
        $linkToken->update(['active' => false]);

        TelegramService::sendToChat(
            $linkToken->chat_id,
            "✅ Questo gruppo è stato collegato dal Master <b>" . htmlspecialchars(Auth::user()->name) . "</b> alla campagna <b>{$campaign->display_name}</b>. Potete iscrivervi con /subscribe.",
            'HTML', null, $linkToken->thread_id
        );

        return view('telegram.link-group-success', compact('campaign'));
    }
}
```

### Route (in `routes/web.php`, gruppo `auth,verified` già esistente)
```php
Route::get('/telegram/link/{token}', [TelegramLinkController::class, 'confirm'])->name('telegram.link.confirm');
Route::post('/telegram/link/{token}/group', [TelegramLinkController::class, 'storeGroupLink'])->name('telegram.link.group.store');
```

### Viste
- `resources/views/telegram/link-success.blade.php`
- `resources/views/telegram/link-invalid.blade.php`
- `resources/views/telegram/link-already-used.blade.php` (collegamento personale conteso)
- `resources/views/telegram/link-not-master.blade.php`
- `resources/views/telegram/link-group-select.blade.php` (form `<select>` campagne, mostra eventuale `currentlyLinked` come preselezionata/segnalata, submit verso `telegram.link.group.store`)
- `resources/views/telegram/link-group-already-linked.blade.php` (la campagna scelta è già di un altro gruppo)
- `resources/views/telegram/link-group-success.blade.php`

Tutte estendono `layouts.app`, stile coerente con `errors.403`/altre pagine semplici già presenti.

---

## 5. Dashboard Admin

### 5.1 Iscrizioni Telegram — nuova pagina `AdminController::telegramSubscribers()`
```php
public function telegramSubscribers(Request $request)
{
    if ($response = $this->checkAdmin()) return $response;

    $query = TelegramSubscriber::with('campaign')->orderBy('created_at', 'desc');
    if ($request->filled('campaign_id')) {
        $query->where('campaign_id', $request->campaign_id);
    }
    $subscribers = $query->paginate(30);

    $telegramUserIds = $subscribers->pluck('telegram_user_id')->filter()->unique();
    $linkedUsers = User::whereIn('telegram_user_id', $telegramUserIds)->get()->keyBy('telegram_user_id');

    $campaigns = Campaign::orderBy('order')->get();
    $totalSubscribers = TelegramSubscriber::count();
    $linkedCount = TelegramSubscriber::whereNotNull('telegram_user_id')->count();

    return view('admin.telegram.index', compact('subscribers', 'linkedUsers', 'campaigns', 'totalSubscribers', 'linkedCount'));
}

public function deleteTelegramSubscriber(TelegramSubscriber $subscriber)
{
    if ($response = $this->checkAdmin()) return $response;
    $subscriber->delete();
    return redirect()->route('admin.telegram.index')->with('success', 'Iscrizione eliminata con successo');
}
```
Per ogni riga della tabella iscrizioni, per capire se è un gruppo o una chat privata si usa `str_starts_with($subscriber->chat_id, '-')`; se è un gruppo, il "collegamento" mostrato è la campagna stessa a cui è già associata la riga (`$subscriber->campaign`), dato che l'esistenza dell'iscrizione presuppone che il gruppo fosse collegato al momento dell'iscrizione — la colonna "Collegamento" mostra quindi "Utente: Nome" per le chat private (o "non collegato" se `telegram_user_id` è nullo) e "Gruppo → {campagna}" per i gruppi.

### 5.2 Gestione del collegamento gruppo↔campagna — dentro `/admin/campaigns`
Non serve una pagina separata: si estende quella già esistente.

- **`admin.campaigns.index`**: aggiungere una colonna "Gruppo Telegram" che mostra il `telegram_chat_id` (troncato) se presente, altrimenti "—", con un bottone "Scollega" quando presente.
- Nuovo metodo in `AdminController`:
```php
public function unlinkTelegramGroup(Campaign $campaign)
{
    if ($response = $this->checkAdmin()) return $response;

    if ($campaign->telegram_chat_id) {
        TelegramSubscriber::where('chat_id', $campaign->telegram_chat_id)
            ->where('thread_id', $campaign->telegram_thread_id)
            ->where('campaign_id', $campaign->id)
            ->delete();

        $campaign->update(['telegram_chat_id' => null, 'telegram_thread_id' => null]);
    }

    return redirect()->route('admin.campaigns')->with('success', 'Gruppo Telegram scollegato dalla campagna');
}
```
- Nuova route (gruppo `admin`):
```php
Route::delete('/campaigns/{campaign}/telegram-group', [AdminController::class, 'unlinkTelegramGroup'])->name('admin.campaigns.telegram-group.delete');
```

### 5.3 Route Iscrizioni
```php
Route::get('/telegram-subscribers', [AdminController::class, 'telegramSubscribers'])->name('admin.telegram.index');
Route::delete('/telegram-subscribers/{subscriber}', [AdminController::class, 'deleteTelegramSubscriber'])->name('admin.telegram.delete');
```

### 5.4 Menu Admin (`resources/views/layouts/app.blade.php`)
```blade
<a class="dropdown-item" href="{{ route('admin.telegram.index') }}">
    <i class="bi bi-telegram"></i> Iscrizioni Telegram
</a>
```
(subito dopo la voce "Campagne", dove ora si trova anche la gestione del collegamento gruppo).

---

## 6. Aggiornamento README.md

Sezione "🤖 Integrazione Telegram Bot & Notifiche Multi-Campagna":
- Aggiornare l'elenco comandi: `/link` e `/unlink` con comportamento diverso per chat privata (collegamento personale) e gruppo (collegamento a UNA campagna, confermato da un Master sul sito); segnare `/search` e `/view` come "temporaneamente disattivati".
- Documentare `telegram_link_tokens` (con `type: personal|group`) e le nuove colonne `campaigns.telegram_chat_id`/`telegram_thread_id` (vincolo: un gruppo ↔ una campagna), oltre a `users.telegram_user_id`/`telegram_username`.
- Documentare che `/subscribe` e `/unsubscribe` si comportano diversamente per chat private (scelta tra le campagne accessibili) e gruppi (unica campagna collegata, se presente).
- Aggiungere `TelegramLinkController` e `TelegramLinkToken` nell'albero della struttura del progetto; annotare in `Campaign` i nuovi campi.

Sezione "🛠️ Pannello di Amministrazione (`/admin`)":
- "Gestione Campagne (`/admin/campaigns`)": aggiungere la menzione del collegamento/scollegamento del gruppo Telegram.
- Aggiungere voce "Iscrizioni Telegram (`/admin/telegram-subscribers`): elenco iscrizioni con collegamento all'utente o al gruppo/campagna, filtro per campagna, eliminazione iscrizioni."

---

## 7. Controllo di accesso all'invio delle notifiche (`TelegramService::broadcastCampaign`)

### 7.1 Nuovo helper `TelegramService::subscriberHasAccess()`
```php
protected static function subscriberHasAccess(\App\Models\TelegramSubscriber $subscriber, \App\Models\Campaign $campaign): bool
{
    $isGroup = str_starts_with((string) $subscriber->chat_id, '-');

    if ($isGroup) {
        return $campaign->telegram_chat_id === $subscriber->chat_id
            && $campaign->telegram_thread_id === $subscriber->thread_id;
    }

    if (!$subscriber->telegram_user_id) {
        return false; // mai collegato, oppure scollegato con /unlink: accesso non verificabile
    }

    $user = \App\Models\User::findByTelegramUserId($subscriber->telegram_user_id);
    if (!$user) {
        return false; // utente eliminato dal sito
    }

    return $user->hasAccessToCampaign($campaign);
}
```

### 7.2 Modifica di `broadcastCampaign()`
```php
public static function broadcastCampaign(\App\Models\Campaign $campaign, string $message, bool $parseHtml = true, $replyMarkup = null): void
{
    $subscribers = \App\Models\TelegramSubscriber::where('campaign_id', $campaign->id)->get();

    foreach ($subscribers as $subscriber) {
        if (!self::subscriberHasAccess($subscriber, $campaign)) {
            self::sendToChat(
                $subscriber->chat_id,
                "⚠️ Non è più possibile confermare l'accesso alla campagna <b>{$campaign->display_name}</b>, quindi l'iscrizione alle notifiche è stata rimossa.",
                'HTML', null, $subscriber->thread_id
            );
            $subscriber->delete();
            continue;
        }

        self::sendToChat($subscriber->chat_id, $message, $parseHtml, $replyMarkup, $subscriber->thread_id);
    }
}
```

### 7.3 Note e casi limite
- **`broadcast()` legacy (senza campagna)**: non viene modificato.
- **Gruppi**: nella pratica, se il collegamento gruppo↔campagna viene rimosso (da `/unlink` nel gruppo o da `/admin/campaigns`), l'iscrizione corrispondente viene già cancellata immediatamente in quel momento (sezioni 3.4 e 5.2). Il controllo lazy a invio-notifica resta comunque utile come rete di sicurezza (es. dati incoerenti, modifiche dirette da SQL console) e per il caso, più frequente, delle chat private.
- Il controllo avviene ad ogni invio (sia da `NotifyVaultUpdate` via CLI, sia da `notifyUpdate` via webhook HTTP).

---

## 8. Checklist di test manuale

1. `/link` in chat privata → riceve bottone → click → redirect a login (se non loggato) → dopo login torna alla pagina di conferma → `users.telegram_user_id`/`telegram_username` valorizzati.
2. `/link` in un gruppo → riceve bottone di collegamento gruppo; click da utente loggato non Master → pagina "solo un Master può completare"; da Master → form di selezione campagna → dopo submit, `campaigns.telegram_chat_id`/`telegram_thread_id` valorizzati e messaggio di conferma inviato nel gruppo.
3. Tentativo di collegare un secondo gruppo a una campagna già collegata a un gruppo diverso → pagina di errore "campagna già collegata a un altro gruppo".
4. Lo stesso gruppo, già collegato alla Campagna A, esegue di nuovo `/link` e sceglie la Campagna B → la Campagna A perde il collegamento (colonne azzerate), la Campagna B lo acquisisce.
5. Riutilizzo di un token già usato (`active = false`), personale o di gruppo → pagina "link non valido". Token scaduto (>1h) → stessa pagina.
6. `/link` personale da un secondo account Telegram verso un account sito già collegato ad altro Telegram → pagina "già collegato ad un altro utente".
7. `/subscribe` in chat privata senza account collegato → messaggio di errore con invito a `/link`. Con account collegato → mostra solo le campagne accessibili (Master: tutte).
8. `/subscribe` in un gruppo non collegato → messaggio che invita a `/link`. In un gruppo collegato → iscrizione diretta, nessuna tastiera di scelta.
9. `/unsubscribe` in chat privata senza account collegato → messaggio di errore. In gruppo → funziona sempre, senza controlli, cancella l'unica iscrizione esistente.
10. `/unlink` personale con conferma → azzera i campi telegram su `users`.
11. `/unlink` in un gruppo da un utente non Master (o non collegato) → messaggio di rifiuto. Da un Master collegato → bottone di conferma; alla conferma, campagna scollegata e iscrizione corrispondente cancellata.
12. `/search` e `/view` → rispondono con messaggio "temporaneamente disattivato".
13. `/admin/campaigns` → colonna gruppo Telegram corretta, azione "Scollega" funzionante (azzera i campi e cancella l'iscrizione).
14. Dashboard `/admin/telegram-subscribers` → conteggi corretti, filtro per campagna funzionante, colonna "collegamento" corretta sia per chat private che per gruppi, eliminazione iscrizione funzionante.
15. Utente privato iscritto esegue `/unlink`, poi si invia una notifica per quella campagna (es. `php artisan vault:notify-update {branch}`) → riceve il messaggio di accesso non più verificabile invece del changelog, riga cancellata da `telegram_subscribers`.
16. Utente privato collegato e iscritto viene rimosso dalla campagna via admin (senza `/unlink`) → alla notifica successiva riceve il messaggio di accesso non verificabile.
17. Gruppo collegato e iscritto a una campagna riceve regolarmente la notifica finché il collegamento esiste; dopo lo scollegamento (via `/unlink` o `/admin/campaigns`) non riceve più nulla (l'iscrizione è già cancellata al momento dello scollegamento).
