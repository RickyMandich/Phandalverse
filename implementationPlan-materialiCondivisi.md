# Piano di Implementazione - Materiali Condivisi tra Campagne

Richiesta originale: gestione centralizzata di materiali di gioco (manuali, bestiario, stat-block) condivisi tra tutte le campagne, oggi duplicati nella cartella `materiale/` di ciascuna campagna; import diretto delle stat-block dalle note nel Fight Manager (`DmCharacter` type `template`).

Decisioni prese (confermate in conversazione):
- Materiali **non è una Campaign nel DB**: nessuna riga in `campagne`, nessuna modifica a `Campaign`/`User`/migrazioni/CRUD admin. È trattata come pseudo-campagna solo lato codice (stringa `'materiali'` ovunque oggi si accetta `Campaign|string|null`), per riusare la grafica e le utility esistenti (albero, note, wikilink, ricerca del singolo file) senza comparire nella select campagne e senza generare un grafo.
- Wikilink cross-vault: **fallback automatico**, nessuna sintassi speciale `[[materiali:...]]`. `[[Nota]]` cerca prima nella campagna corrente, poi in materiali. Resta 100% compatibile con Obsidian.
- Niente collegamento DB↔nota per i mostri importati: la nota è solo fonte iniziale, il record `DmCharacter` vive di vita propria dopo l'import.
- Niente sistema di gruppi di accesso per materiali (è tutta pubblica). Il filtro `#dm` (master-only) resta invece attivo, perché è un meccanismo binario indipendente dai gruppi e alcune stat-block esistenti lo usano già per nascondere info di regia.
- Scan della cartella stat-block **on-demand** (quando il master apre la schermata di import), nessun webhook/cron dedicato: l'aggiornamento del vault avviene già al push sul branch, e uno scan un po' lento è accettabile visto che l'import è pensato per batch.
- Pulsante "Materiali" nell'header, di fianco a "Vault".

Ho esplorato il codice sorgente per validare il piano (non solo il template): `VaultController`, `VaultHelper`, `MarkdownPreprocessor`, `AccessControlService`, `Campaign`, `User`, `DmController`, `DmCharacter` + migrazione, `routes/web.php`, `layouts/app.blade.php`, `docker-compose.yml`, la GitHub Action e `bash/normalizeREADME.md` del repo vault, e un'istanza reale di stat-block (`mind-flayer.md`) oltre al template.

---

## 0. Cosa emerge dal codice esistente (base di partenza favorevole)

- Quasi tutte le funzioni di basso livello già accettano `Campaign|string|null $campaign` e usano internamente `VaultHelper::resolveCampaignFolder()`: `VaultHelper::loadMap/getOriginalName/searchNotes/getPdfAccessTag`, `AccessControlService::noteIsVisibleTo/pdfIsVisibleTo/userHasCampaignAccess`, `MarkdownPreprocessor::buildFileIndex/findNotePath/convertWikilinks/toHtml`. Passare la stringa `'materiali'` a queste funzioni **funziona già oggi senza modifiche**.
- Il volume Docker monta l'intera cartella `./Vault` (non una sottocartella per campagna): basta che il branch `materiali` venga clonato in `Vault/materiali/` per essere immediatamente visibile ai container, **nessuna modifica a `docker-compose.yml`**.
- La GitHub Action del repo vault (`on: push branches: ['**']` → `pull-vault.sh ${{ github.ref_name }}`) è già generica per qualsiasi branch, così come `pull-vault.sh` lato server: **nessuna modifica infrastrutturale**, basta creare il branch `materiali` e pushare.
- La pipeline bash (`normalize.sh`, `cmt.sh`, `all.sh`) è generica e opera sulla cartella corrente: basta che il branch `materiali` abbia la propria copia di `bash/` (come ogni altro branch/campagna) per generare il proprio `.normalize/map.json`.
- `DmCharacter` (type `template`) è **già pensato per essere condiviso**: `getCharacters()` restituisce "Public templates + User's own", quindi i mostri importati da materiali sono automaticamente visibili a tutti i master senza nessuna modifica al modello.
- La migrazione `dm_characters` documenta già la forma di `stats`: `attributes, ac, hp_formula, saving_throws, notes, link`. Il campo `notes` è già gestito con visibilità ristretta per chi non è `isMasterUtils()` — perfetto come "contenitore" per tutto ciò che non parsiamo in campi strutturati.
- **Attenzione**: `VaultController::show()` oggi fa `if (!Auth::check()) return redirect('login')` anche per la home/albero di una campagna. Materiali deve restare consultabile **da anonimo**, quindi non può riusare `show()`/`index()` così come sono: serve un controller dedicato che non impone il login.

---

## 1. Nuovo branch/cartella `materiali`

- Il branch `materiali` andrà creato come sibling dei branch di campagna nel repo `PhandalverseVault` (stesso volume di storage, stessa GitHub Action, stesso script `pull-vault.sh` — nessuna azione di mia competenza qui, la gestisci tu come da tua richiesta).
- Struttura minima attesa dentro il branch, per essere compatibile con `VaultHelper`/`MarkdownPreprocessor`:
  - `.normalize/map.json` (generato da `bash/normalize.sh`, stessa pipeline delle campagne)
  - `stat-block/*.md` — le note stat-block da cui importare (path esplicitamente citato nella richiesta originale)
  - resto dei materiali liberi (manuali, incantesimi, ecc.)
- Non copio io il contenuto delle cartelle `materiale/` esistenti nelle campagne: come richiesto, mi limito al codice.

---

## 2. `App\Helpers\VaultHelper` — piccola estensione

- Nuovo metodo `resolveCampaignDisplayName(Campaign|string|null $campaign): string`: ritorna `$campaign->display_name` se è una `Campaign`, altrimenti un nome leggibile per le stringhe speciali (`'materiali'` → `'Materiali'`), riusando `prettify()` già esistente. Serve per i titoli di pagina (`'Vault - ' . $campaign->display_name` oggi esploderebbe se `$campaign` è una stringa).
- Nessun'altra modifica: `resolveCampaignFolder`, `loadMap`, `getOriginalName`, `searchNotes`, `getPdfAccessTag` già funzionano con una stringa.

---

## 3. `App\Http\Controllers\VaultController.php` — generalizzare i type-hint

Cambio chirurgico, senza toccare il comportamento per le campagne vere:

- `buildFileTree()`, `traverseMapAndBuildTree()`, `loadGraphConfig()`, `buildGraphData()`, `camelCaseToFolderPath()`: cambiare la firma da `?Campaign $campaign` a `Campaign|string|null $campaign` (nessun'altra modifica al corpo: usano già `VaultHelper::resolveCampaignFolder()` e il pattern `($campaign instanceof Campaign) ? $campaign->id : null` per tutto ciò che è specifico del DB).
- `loadGraphConfig()`: per materiali non verrà mai chiamato (niente grafo), ma renderlo type-safe evita di dover duplicare altrove logica già scritta.
- Nessuna modifica a `show()`, `rawShow()`, `search()`, `index()`: restano dedicati alle vere campagne con route model binding su `Campaign`.

---

## 4. Nuovo `App\Http\Controllers\MaterialiController.php`

Estende `VaultController` per riusare `buildFileTree()`/`traverseMapAndBuildTree()` (diventati `protected`/`public` e ora string-friendly) invece di duplicarli. Passa sempre la stringa letterale `'materiali'` dove serve `Campaign|string|null`.

- `index()`: home con solo vista ad albero (niente grafo — non richiesto). **Nessun redirect al login**: `Auth::user()` può essere `null`, tutte le chiamate sotto (`AccessControlService::noteIsVisibleTo`, ecc.) già supportano `$user = null` trattandolo come guest pubblico.
- `show($note = null)`: stessa logica di risoluzione nota/cartella/pdf di `VaultController::show()` ma:
  - senza il gate `Auth::check()`;
  - senza `checkCampaignAccess()` (non esiste una `Campaign` a cui verificare l'accesso);
  - il tag `#dm` continua a nascondere la nota ai non-master (comportamento voluto, vedi decisioni sopra);
  - la sidebar/select campagne mostrata nella vista (`accessibleCampaigns`) resta quella dell'utente loggato (o vuota per guest), per permettere di tornare a una campagna vera.
- `raw($note)`: equivalente di `rawShow()`, pubblico, stesse regole.
- Non implemento un `search()` dedicato per materiali in questo giro: resta fuori scope (la ricerca esistente resta per-campagna). Lo segnalo come possibile estensione futura, non bloccante.

---

## 5. Rotte (`routes/web.php`)

```php
Route::prefix('materiali')->group(function () {
    Route::get('/{note?}', [MaterialiController::class, 'show'])
        ->where('note', '.*')
        ->name('materiali.show');
});

Route::get('/api/materiali/{note?}', [MaterialiController::class, 'raw'])
    ->where('note', '.*')
    ->name('materiali.raw');
```

Dichiarate **fuori** da qualunque middleware `auth`, coerentemente con l'accesso pubblico. `materiali.show` senza `note` risolve alla home (albero).

---

## 6. Wikilink cross-vault con fallback

In `MarkdownPreprocessor::convertWikilinks()` e `findNotePath()`: oggi la risoluzione usa solo `buildFileIndex($campaign)` (indice della campagna corrente). Aggiungo un fallback:

1. Cerca `$cleanName` nell'indice della campagna corrente (comportamento attuale, invariato).
2. Se non trovato, cerca nell'indice di `buildFileIndex('materiali')` (stessa cache statica per-richiesta di `$fileIndices`, chiave `'materiali'`, già supportata dalla struttura esistente).
3. Se trovato in materiali, il link generato punta a `/materiali/{path}` invece che a `/vault/{folder}/{path}` — serve distinguere l'origine per costruire l'URL giusto, quindi la funzione interna dovrà propagare non solo il path ma anche "da dove viene" (piccola modifica di ritorno, non solo stringa ma coppia `[path, origin]`).
4. Stesso fallback in `loadEmbedContent()` (per gli embed `![[...]]`) e in `getOriginalName()`/label del link, per coerenza.

Effetto pratico: una nota di campagna può linkare `[[Manuale dei Mostri]]` (o incorporarla con `![[...]]`) senza sapere se vive nella campagna o in materiali, e il link resta verde in Obsidian perché è un wikilink normale.

**Nota**: priorità campagna-locale prima di materiali, come deciso, per evitare ambiguità quando esistono note omonime.

---

## 7. Header — pulsante "Materiali"

In `resources/views/layouts/app.blade.php`, il link "Vault" vive dentro `@auth` (perché il resto della nav per guest è minimale). Materiali deve essere raggiungibile anche da **utente non loggato**, quindi:

- Aggiungo un `<li>` "Materiali" **fuori** dal blocco `@auth`, sempre visibile (posizionato subito prima del blocco `@auth`/`@guest` così risulta visivamente adiacente a "Vault" quando l'utente è loggato, e resta comunque presente da solo per i guest).
- Stessa icona/stile di "Vault" (`bi-folder2-open` per Vault, propongo `bi-collection` o `bi-journals` per Materiali per differenziarli a colpo d'occhio).

---

## 8. Import stat-block → Fight Manager

### 8.1 Parsing (`App\Services\StatBlockParser`, nuovo)

Dal confronto tra il template (`definizioni/template/stat-block.md`) e un'istanza reale (`materiale/stat-block/mind-flayer.md`) risulta che **le note reali si discostano parecchio dal template**: sezioni aggiuntive libere (Tiri Salvezza, Abilità, Sensi, Linguaggi, sfida, varianti), formattazione non sempre identica, wikilink interni agli incantesimi. Parsare rigidamente tutto sarebbe fragile e si romperebbe alla prima nota "fuori schema".

Approccio pragmatico, coerente con la forma già prevista da `stats` in `dm_characters`:
- **Campi strutturati** (quelli con un pattern regex affidabile nel template, presenti in ogni nota): nome (prima intestazione `#`/`##` dentro il blockquote), specie/taglia/allineamento (riga in corsivo sotto il nome), `ac` (Classe Armatura), `hp_formula` (Punti Vita: media + formula dadi), velocità, tabella delle 6 caratteristiche (`attributes`: FOR/DES/COS/INT/SAG/CAR con punteggio e bonus).
- **Tutto il resto** (competenze, tiri salvezza, sensi, linguaggi, sfida, azioni, azioni bonus, reazioni, varianti, wikilink agli incantesimi) va dentro `notes` come testo grezzo (markdown originale della sezione, non riprocessato) — esattamente il campo già pensato per contenuto libero e già soggetto a `[ACCESSO LIMITATO]` per chi non è `isMasterUtils()`.
- Il tag `#dm` in testa alla nota, se presente, non influenza l'import (il record `DmCharacter` è comunque visibile solo dietro il middleware `master_utils` del Fight Manager) ma verrà comunque riportato come promemoria in `notes`.

### 8.2 Scan on-demand

- Nuovo endpoint `GET /dm/api/materiali/stat-blocks` (dentro il gruppo di rotte già protetto da `auth`+`verified`+`master_utils`): scansiona `Vault/materiali/stat-block/*.md`, parsa ciascun file con `StatBlockParser`, e per ciascuno controlla se esiste già un `DmCharacter` (`type = 'template'`) con lo stesso nome (case-insensitive). Ritorna un elenco `{path, name, conflict: bool}`.
- Nessuna scrittura né cache persistente: rifatto ad ogni apertura della schermata, accettabile come da tua indicazione (il batch ammortizza il costo).

### 8.3 UI di selezione (in `dm.manage` — "Gestione Risorse")

- Nuovo pulsante "Importa da Materiali" che apre una lista (checkbox multiple) dei risultati dello scan.
- Per ogni riga in conflitto, un piccolo controllo per riga: **Sovrascrivi** (aggiorna il record esistente) o **Duplica** (crea un nuovo record con nome "Nome (2)", "Nome (3)", ... calcolato lato server come fa Esplora File, cercando il primo suffisso libero tra i nomi esistenti).
- Import multiplo in un'unica richiesta.

### 8.4 Endpoint di import

- Nuovo `POST /dm/api/materiali/stat-blocks/import`, body: array di `{path, resolution: 'skip'|'overwrite'|'duplicate'}`.
- Per ciascun elemento non "skip": ri-parsa il file, costruisce `stats` (`attributes`, `ac`, `hp_formula`, `notes`, ecc.), e:
  - nessun conflitto → `DmCharacter::create(['user_id' => Auth::id(), 'name' => ..., 'type' => 'template', 'stats' => ...])`;
  - `overwrite` → `update()` sul record esistente trovato per nome;
  - `duplicate` → crea con nome suffissato al primo numero libero.
- Nessun campo che colleghi il record alla nota sorgente (come deciso): da questo momento il `DmCharacter` vive indipendente.

---

## 9. Decisioni finali (confermate)

1. **Nome cartella/branch/route**: **`materiale`** (singolare), allineato alla cartella per-campagna già esistente (`cronacheIntrecciate/materiale/`). Uso questo slug per il branch, la route (`/materiale`) e il path su disco (`Vault/materiale/`). Non rinomino la classe `MaterialiController` né i metodi interni (nessun beneficio pratico, solo lavoro in più) — l'etichetta visibile all'utente sarà comunque "Materiali" (plurale, coerente con altre etichette di sezione come "Manuali"), esattamente come `folder_name` (slug tecnico) e `display_name` (etichetta) già divergono per le campagne.
2. **Nessun filtro `#dm` su materiale**: il tag `#dm` presente oggi in `mind-flayer.md` è un errore di battitura dell'utente in quella nota specifica, non una scelta di design — verrà rimosso a parte. **Non aggiungo nessuna logica di eccezione nel codice**: `MaterialiController` userà comunque `AccessControlService::noteIsVisibleTo()`/`isDmOnly()` esattamente come `VaultController` (stesso meccanismo, riuso puro), che di per sé ritornano "pubblico" per qualunque nota priva di tag `#dm`/`#access-...`. Con la rimozione del tag sbagliato, materiale risulta interamente pubblica senza bisogno di alcun ramo di codice dedicato.
3. **Pulsante header**: stessa icona di "Vault" (`bi-folder2-open`), stesso stile/classi del link Vault — etichetta "Materiali".

Tutti i punti aperti sono risolti: procedo con l'esecuzione (punto 3 del workflow) e aggiorno `todo.md`/`README.md` di conseguenza.

---

## Piano di Verifica

- `php -l` sui file nuovi/modificati.
- Da **guest** (non loggato): `/materiali` mostra l'albero, apre una nota pubblica, non mostra una nota taggata `#dm`.
- Da **utente loggato non master**: stesso comportamento del guest per materiali (nessun accesso a gruppi da verificare, dato che non esistono per materiali).
- Da **master**: vede anche le note `#dm` in materiali.
- Un wikilink `[[Nome Nota Materiali]]` scritto dentro una nota di una campagna reale risolve verso `/materiali/...` quando la nota non esiste nella campagna corrente, e resta verde in Obsidian.
- Import: file senza conflitto → nuovo `DmCharacter` visibile in "Gestione Risorse" a tutti i master; file con nome già esistente → comportamento corretto sia per "Sovrascrivi" che per "Duplica" (suffisso incrementale corretto anche con più duplicati già presenti, es. se esiste già "Goblin (2)" il prossimo deve diventare "Goblin (3)").
- La select campagne nell'header/sidebar non elenca mai "materiali".
- Nessuna regressione sulle campagne vere (home, albero, note, pdf, ricerca, changelog) dopo il refactor dei type-hint in `VaultController`.

---

## Esecuzione — note finali

Eseguito. Scostamenti minori rispetto al piano, per pragmatismo durante l'implementazione:
- **Nessuna classe `MaterialiController` separata**: le rotte pubbliche puntano a due nuovi metodi in `VaultController` stesso (`showMateriale`/`rawShowMateriale`), che delegano a due metodi protetti estratti dal corpo originale di `show()`/`rawShow()` (`renderVaultNote()`/`renderVaultRaw()`, con un flag `$publicShared` che disattiva i redirect al login). Stesso risultato del piano, meno duplicazione di codice.
- **Bug scoperto in corso d'opera e corretto**: `User::hasAccessToCampaign()` faceva fallire il controllo di accesso di `AccessControlService::noteIsVisibleTo()`/`pdfIsVisibleTo()` per qualunque **utente loggato non master** che visitasse materiale (cercava una riga `campaigns.folder_name = 'materiale'` inesistente e restituiva `false`) — i guest non erano affetti perché quel controllo si applica solo se `$user !== null`. Aggiunta un'eccezione esplicita `if ($campaign === 'materiale') return true;` in testa al metodo.
- **Fallback wikilink**: propagata anche la campagna di provenienza (non solo il path) fino a `loadEmbedContent()`/`toHtml()` ricorsivo, così un embed risolto in materiale viene poi renderizzato/collegato nel contesto corretto invece che in quello della campagna di partenza.
- **Import stat-block**: implementato interamente come da piano (parser, scan, import batch con sovrascrivi/duplica, UI in `dm.manage`).
- **Decisioni finali del punto 9** applicate: slug `materiale` (singolare) ovunque lato URL/disco, nessuna eccezione per `#dm` nel codice (il tag va rimosso a parte dalla nota reale), icona header identica a "Vault".

File creati: `app/Services/StatBlockParser.php`.
File modificati: `app/Helpers/VaultHelper.php`, `app/Http/Controllers/VaultController.php`, `app/Http/Controllers/DmController.php`, `app/Models/User.php`, `app/Services/MarkdownPreprocessor.php`, `routes/web.php`, `resources/views/layouts/app.blade.php`, `resources/views/vault/note.blade.php`, `resources/views/vault/_sidebar.blade.php`, `resources/views/dm/manage.blade.php`, `README.md`, `todo.md`.

Non fatto (fuori scope per scelta esplicita, vedi sopra): migrazione del contenuto reale delle cartelle `materiale/` di ogni campagna nel nuovo branch condiviso, rimozione del tag `#dm` errato in `mind-flayer.md`, test end-to-end eseguiti a mano (nessun accesso `php artisan`/terminale disponibile in questo ambiente).

