# 🌌 Phandalverse - Documentazione Tecnica & Guida per lo Sviluppatore

Benvenuto nel repository di **Phandalverse**, la piattaforma web integrata e companion app per la gestione di campagne D&D / RPG, worldbuilding e strumenti di gioco.

Questa documentazione fornisce una panoramica completa sull'architettura, le scelte di design, i moduli principali e le modalità di gestione delle risorse, permettendo a qualsiasi sviluppatore di comprendere ed entrare direttamente nello sviluppo del progetto senza necessità di ulteriori spiegazioni.

---

## 📋 Indice
1. [🌌 Introduzione e Visione del Progetto](#-introduzione-e-visione-del-progetto)
2. [🏛️ Architettura del Sistema & Struttura del Codice](#%EF%B8%8F-architettura-del-sistema--struttura-del-codice)
3. [🎲 Architettura Multi-Campagna (Multi-Branch Vault)](#-architettura-multi-campagna-multi-branch-vault)
   - [Database & Modello `Campaign`](#database--modello-campaign)
   - [Sottocartelle Vault e Risoluzione File](#sottocartelle-vault-e-risoluzione-file)
   - [Scoping delle Rotte Web & Reindirizzamento Iniziale](#scoping-delle-rotte-web--reindirizzamento-iniziale)
   - [Matrice dei Permessi di Accesso alle Campagne](#matrice-dei-permessi-di-accesso-alle-campagne)
4. [📚 Gestione del Vault (Obsidian Integration Engine)](#-gestione-del-vault-obsidian-integration-engine)
   - [Normalizzazione dei Percorsi e `map.json` per Campagna](#normalizzazione-dei-percorsi-e-mapjson-per-campagna)
   - [Engine Markdown (`MarkdownPreprocessor`)](#engine-markdown-markdownpreprocessor)
   - [Permessi di Lettura e Gruppi di Accesso (`#access-`, `#dm`, `#startAccess-`)](#permessi-di-lettura-e-gruppi-di-accesso-access--dm-startaccess-)
   - [Pannello ad Albero, Selettore Campagne e Grafo Interattivo](#pannello-ad-albero-selettore-campagne-e-grafo-interattivo)
   - [Ricerca, API Raw e Embed Immagini Scoped](#ricerca-api-raw-e-embed-immagini-scoped)
5. [⚔️ DM Screen & Gestione Sessioni D&D (`/dm`)](#%EF%B8%8F-dm-screen--gestione-sessioni-dd-dm)
   - [Iniziative, Turni e Stat Block](#iniziative-turni-e-stat-block)
   - [Libreria Personaggi (`DmCharacter`)](#libreria-personaggi-dmcharacter)
   - [Player Live View (`/dm/player/{share_code}`)](#player-live-view-dmplayershare_code)
6. [👤 Sistema di Autenticazione, Ruoli e Profilo Utente](#-sistema-di-autenticazione-ruoli-e-profilo-utente)
7. [✈️ Coda Email Asincrona (Fire-and-Forget su Hosting Limiti)](#%EF%B8%8F-coda-email-asincrona-fire-and-forget-su-hosting-limiti)
8. [🤖 Integrazione Telegram Bot & Notifiche Multi-Campagna](#-integrazione-telegram-bot--notifiche-multi-campagna)
9. [🛠️ Pannello di Amministrazione (`/admin`)](#%EF%B8%8F-pannello-di-amministrazione-admin)
10. [🪵 Sistema di Logging Personalizzato (`CustomLogger`)](#-sistema-di-logging-personalizzato-customlogger)
11. [🚀 Workflow di Sviluppo, Deployment e Regole Server](#-workflow-di-sviluppo-deployment-e-regole-server)

---

## 🌌 Introduzione e Visione del Progetto

Phandalverse nasce con un'idea di fondo chiara: **trasformare i Vault di Obsidian (file Markdown) in una piattaforma web dinamica, interattiva, multi-campagna e multi-utente**, affiancata da strumenti di ausilio per il Dungeon Master (DM Screen) e per i giocatori (Player View in tempo reale).

### Concetti Chiave:
- **Obsidian come Single Source of Truth**: L'enciclopedia del mondo di gioco è scritta in Markdown e posizionata sul server nella cartella `/Vault/{folder_name}` per ciascuna campagna. L'applicazione web la trasforma in pagine HTML consultabili con supporto a Wikilink, Graph View, visualizzazione immagini, changelog delle versioni e permessi differenziati.
- **Multi-Campagna su Singolo Dominio**: Permette la coesistenza di più campagne/mondi narrativi su un unico dominio (`phandalverse.mandich.dev`), ciascuno isolato nella propria sottocartella, con gruppi di accesso dedicati, assegnazione utenti e notifiche Telegram indipendenti.
- **Supporto ad Hosting Condivisi (Altervista)**: Il sistema è progettato per funzionare senza demoni CLI sempre attivi (`php artisan queue:work` o SMTP dedicati). La coda email, le notifiche e la gestione log utilizzano meccanismi *fire-and-forget* via socket/cURL interni e middleware parassita.
- **Workflow Diretto in Produzione via FTP**: Gli aggiornamenti sul server di produzione vengono distribuiti tramite script FTP automatizzati basati su Git (`bash/`).

---

## 🏛️ Architettura del Sistema & Struttura del Codice

Il progetto è costruito sul framework **Laravel 12** con frontend basato su **Blade**, **Bootstrap 5**, **Sass**, **Vite** e librerie JavaScript specializzate (es. **D3.js / Force Graph** per il grafo delle note).

```
phandalverse/
├── app/
│   ├── Helpers/
│   │   └── VaultHelper.php          # Risoluzione map.json per campagna, nomi originali, ricerca note
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AdminController.php  # Gestione campagne, utenti, gruppi, errori, SQL console, stats
│   │   │   ├── DmController.php     # Logica DM Screen, sessioni combat, player view
│   │   │   ├── VaultController.php  # Rendering note, albero, grafo interattivo, API raw per campagna
│   │   │   ├── TelegramBotController.php # Webhook telegram, comandi, notifiche multi-campagna
│   │   │   ├── JobController.php    # Processore asincrono della coda email
│   │   │   ├── ReportController.php # Segnalazioni utenti
│   │   │   └── ChangelogController.php # Visualizzazione versioni del vault per campagna
│   │   └── Middleware/
│   ├── Jobs/                        # Job per invio mail in coda
│   ├── Models/
│   │   ├── Campaign.php             # Modello Campagna (folder_name, display_name, order)
│   │   ├── User.php                 # Modello Utente (Admin, Master, default_campaign_id, accessibleCampaigns)
│   │   ├── AccessGroup.php          # Gruppi di accesso gerarchici al Vault legati a campaign_id
│   │   ├── DmSession.php            # Sessione di combattimento (dati JSON + share_code)
│   │   ├── DmCharacter.php          # Template e PG/PNG salvati
│   │   ├── SystemError.php          # Log errori di sistema per admin
│   │   ├── SystemSetting.php        # Impostazioni di sistema (es. default vault view)
│   │   ├── Statistic.php            # Statistiche visite e tempo di risposta
│   │   └── TelegramSubscriber.php   # Iscritti alle notifiche telegram per campaign_id
│   └── Services/
│       ├── AccessControlService.php # Controllo accessi unificato note/blocchi e gerarchia gruppi
│       ├── MarkdownPreprocessor.php # Engine di parsing Markdown (Wikilink, Embed, AccessBlock, Statblock)
│       ├── TelegramService.php      # Client API Telegram (invio messaggi, broadcastCampaign, notifiche)
│       ├── EmailQueueService.php    # Dispatcher coda email
│       ├── EmailLogService.php      # Logger dedicato sistema mail
│       └── CustomLogger.php         # Logger multi-canale su file (notes, graph, screen, telegram)
├── bash/                            # Script di deployment Git + FTP su server
├── config/                          # Configurazione Laravel
├── database/
│   ├── migrations/                  # Migrazioni DB (CON COMMENTO SQL PER PRODUZIONE)
│   └── seeders/
├── resources/
│   ├── js/                          # Javascript per DM Screen, Graph View, ecc.
│   ├── sass/ & css/                 # Stili CSS personalizzati e Bootstrap
│   └── views/
│       ├── admin/                   # Dashboard amministrativa (campagne, utenti, gruppi, errori, db, stats)
│       ├── dm/                      # Schermate DM Screen, Manage e Player View
│       ├── vault/                   # Note, albero side-bar, grafo, ricerca, changelog
│       └── layouts/                 # Master layout dell'applicazione
├── routes/
│   └── web.php                      # Rotte web, scoping campagne, API pubbliche e webhook
└── Vault/                           # Cartella del Vault (PRESENTE SOLO SUL SERVER DI PRODUZIONE)
    ├── newCampaign/                 # Vault della campagna principale
    └── secondCampaign/              # Vault di un'altra campagna
```

---

## 🎲 Architettura Multi-Campagna (Multi-Branch Vault)

Phandalverse supporta nativamente la gestione di **più campagne contemporaneamente** sullo stesso dominio.

### Database & Modello `Campaign`
La tabella `campaigns` memorizza le informazioni di configurazione:
- `id`: Chiave primaria auto-incrementale.
- `folder_name`: Identificatore univoco e nome della sottocartella su disco (es. `newCampaign`, `secondCampaign`), corrispondente al nome del branch git.
- `display_name`: Nome leggibile per l'interfaccia utente e notifiche (es. `Phandalin`, `Kokytos`).
- `order`: Intero univoco che definisce l'ordine di priorità e visualizzazione nei menu.

#### Relazioni tra Modelli:
- **`Campaign <-> User`**: Relazione Many-to-Many tramite la tabella pivot `campaign_user` (`campaign_id`, `user_id`).
- **`User -> defaultCampaign`**: Relazione `belongsTo` tramite colonna `users.default_campaign_id`.
- **`Campaign -> AccessGroup`**: Relazione One-to-Many tramite `access_groups.campaign_id`. Gli slug dei gruppi (`#access-slug`) sono unici per ciascuna campagna.
- **`Campaign -> TelegramSubscriber`**: Relazione One-to-Many tramite `telegram_subscribers.campaign_id`.

### Sottocartelle Vault e Risoluzione File
Sul server di produzione, ciascuna campagna ha la propria directory isolata:
- `Vault/{folder_name}/`: Contiene i file `.md` e gli allegati della campagna.
- `Vault/{folder_name}/.normalize/map.json`: Mappa dei nomi originali e dell'albero file della campagna.
- `Vault/{folder_name}/.normalize/changelogs/`: Storico dei changelog e versioni della campagna.
- `Vault/{folder_name}/.obsidian/`: Configurazioni di Obsidian e del grafo (`graph.json` o `graph-config.json`).

> **Fallback di retrocompatibilità**: Se la sottocartella `Vault/{folder_name}/` non è ancora presente su disco, i controller e gli helper effettuano il fallback alla cartella radice `Vault/`.

### Scoping delle Rotte Web & Reindirizzamento Iniziale
Le rotte del Vault sono strutturate come segue:
- **`/vault`**: Punto di ingresso generico. Reindirizza automaticamente alla campagna iniziale dell'utente:
  1. Se l'utente ha impostato una `default_campaign_id` nel suo profilo, viene usata quella.
  2. Altrimenti, viene selezionata la campagna accessibile con il valore di `order` più basso.
  3. Per i visitatori anonimi, reindirizza alla prima campagna pubblica per `order`.
- **`/vault/{campaign:folder_name}`**: Homepage del Vault per la campagna (grafo interattivo + albero).
- **`/vault/{campaign:folder_name}/{note}`**: Visualizzazione della nota o cartella all'interno della campagna.
- **`/vault/{campaign:folder_name}/search`**: Ricerca note circoscritta alla campagna.
- **`/vault/{campaign:folder_name}/changelog`**: Changelog e cronologia versioni della campagna.
- **`/api/vault/{campaign:folder_name}/{note}`**: Download del Markdown raw per la campagna.

### Matrice dei Permessi di Accesso alle Campagne
- **Master (`isMaster()`) e Admin (`isAdmin()`)**: Hanno accesso universale a tutte le campagne, note `#dm`, note con `#access-...` e strumenti di amministrazione.
- **Giocatori Registrati**: Possono visualizzare le note pubbliche e i gruppi assegnati all'interno delle sole campagne a cui sono stati associati (`campaign_user`). Se tentano di accedere a una campagna non autorizzata, ricevono `403 Forbidden`.
- **Visitatori Anonimi (Guest)**: Possono consultare i contenuti pubblici (senza `#dm` e senza `#access-...`) delle campagne.

---

## 📚 Gestione del Vault (Obsidian Integration Engine)

### Normalizzazione dei Percorsi e `map.json` per Campagna
I file e le cartelle nel Vault reale su disco sono normalizzati (nomi minuscoli, trattini al posto degli spazi, senza caratteri speciali) per garantire la massima compatibilità tra file system (Windows/Linux) e URL puliti.

- **`Vault/{folder_name}/.normalize/map.json`**: Mantiene l'albero di associazione tra i percorsi normalizzati e i titoli/nomi originali (con maiuscole, accenti e spazi) per la specifica campagna.
- **`App\Helpers\VaultHelper`**:
  - `loadMap(?Campaign|string $campaign)`: Carica e memorizza in cache per-request la mappa della campagna indicata.
  - `getOriginalName($normalizedPath, $note, $campaign)`: Recupera il nome formattato originale partendo dal path normalizzato per la campagna.
  - `getOriginalDirectoryName($dirPath, $campaign)`: Restituisce il nome reale di una cartella.
  - `searchNotes($query, $note, $campaign)`: Esegue la ricerca ricorsiva all'interno del `map.json` della campagna.

### Engine Markdown (`MarkdownPreprocessor`) & Access Control (`AccessControlService`)
Il servizio [`App\Services\MarkdownPreprocessor`](file:///c:/Users/RickyMandich/PROJECT/Phandalverse/phandalverse/app/Services/MarkdownPreprocessor.php) estende `League\CommonMark` e implementa la conversione da Markdown ad HTML avanzato, integrato con [`App\Services\AccessControlService`](file:///c:/Users/RickyMandich/PROJECT/Phandalverse/phandalverse/app/Services/AccessControlService.php):

1. **Wikilink (`[[Nome Nota|Alias]]`)**:
   - Convertiti in tag `<a href="/vault/{folder_name}/path-normalizzato" class="wikilink">Alias</a>`.
   - Se la nota target non esiste nell'indice della campagna o l'utente non ha i permessi per vederla, il wikilink viene renderizzato come semplice testo senza link.
2. **Note Embed (`![[Nome Nota]]` o `![[Nome Nota#Sezione]]`)**:
   - Carica e renderizza ricorsivamente la nota o sezione specificata dentro un box pieghevole (`.embed-note`), gestendo la profondità massima (`$maxEmbedDepth = 3`) per evitare loop infiniti.
   - **Verifica permessi ricorsiva**: ogni nota embeddata riesegue il check di visibilità in isolamento per sé stessa nel contesto della campagna corrente.
3. **Image Embed (`![[immagine.png]]` o `![[cartella/immagine.jpg]]`)**:
   - Cerca l'immagine nella cartella `Vault/{folder_name}/` e genera il tag `<img src="/vault/{folder_name}/path-encodato" class="wikilink-image">`.
4. **Obsidian Callouts & Stat Blocks**:
   - Converte i tag `#tag` in badge stilizzati (`<span class="obsidian-tag">`).
   - Trasforma le citazioni `> ...` nei tipici **Stat Block** in stile D&D.
5. **Numeri Romani**:
   - Renderizza le sequenze `R|IX|` nel formato stilizzato per le ere/capitoli.

### Permessi di Lettura e Gruppi di Accesso (`#access-`, `#dm`, `#startAccess-`)
Il Vault gestisce un sistema granulare di visibilità parametrizzato sui **Gruppi di Accesso** (`access_groups`) scoped per campagna:
- **Note intere riservate (`#access-gruppo1_gruppo2` o `#dm`)**:
  - Inserito all'inizio o nel corpo della nota. Più gruppi possono essere specificati separati da underscore `_` (logica OR).
  - Gli slug dei gruppi sono in formato **camelCase** (es. `cavalieriDelDrago`, `artefici`) e sono unici per ciascuna campagna.
  - Se l'utente appartiene a uno dei gruppi indicati (o a un loro gruppo discendente) oppure è Master, la nota è visibile. Altrimenti il server restituisce `404 Not Found` (invisibilità totale, esclusa anche dall'albero, dal grafo e dalla ricerca).
  - Il tag `#dm` agisce come gruppo implicito riservato esclusivamente ai Master.
  - **Badge di visibilità su Sideboard & Header Nota**: quando una nota è riservata, la **sideboard** e l'**header della nota** mostrano i badge colorati dei gruppi tramite cui l'utente ha accesso (o tutti i gruppi taggati per il Master), oltre al badge `Master` per le note `#dm`.
- **Blocchi parziali riservati (`#startAccess-gruppo1_gruppo2 ... #endAccess` e `#startMaster ... #endMaster`)**:
  - **Per chi ha accesso**: il contenuto interno viene renderizzato con un bordo colorato calcolato dalla gerarchia (`resolveBlockColor`) e con badge personalizzati per l'utente (`computeBadgeGroups`).
  - **Per chi non ha accesso**: il blocco viene interamente rimosso prima del rendering HTML.
- **Ereditarietà Gerarchica dei Gruppi**:
  - Chi appartiene a un gruppo *figlio* (es. `bibliotecari`) eredita l'accesso ai contenuti del gruppo *padre* (es. `artefici`). Il padre non vede i contenuti del figlio.

### Pannello ad Albero, Selettore Campagne e Grafo Interattivo
- **Selettore Campagne nella Sidebar**: In cima alla barra laterale del Vault è presente un dropdown dinamico che mostra tutte le campagne accessibili all'utente. La selezione reindirizza immediatamente a `/vault/{selected_campaign}`.
- **Vista ad Albero (`VaultController@buildFileTree`)**: Costruisce la navigazione laterale analizzando il `map.json` della campagna ed escludendo tutte le note non visibili per l'utente corrente tramite `AccessControlService::noteIsVisibleTo()`.
- **Grafo Interattivo (`VaultController@buildGraphData` e `graph.blade.php`)**:
  - Analizza tutti i file `.md` visibili all'utente nella cartella della campagna e ne estrae le connessioni.
  - Legge la configurazione estetica da `Vault/{folder_name}/.obsidian/graph-config.json` o `graph.json`.

---

## ⚔️ DM Screen & Gestione Sessioni D&D (`/dm`)

Il modulo DM Screen ([`App\Http\Controllers\DmController`](file:///c:/Users/RickyMandich/PROJECT/Phandalverse/phandalverse/app/Http/Controllers/DmController.php)) fornisce una console completa per la gestione dei combattimenti e dei turni di gioco.

### Iniziative, Turni e Stat Block
- **Console Master (`/dm`)**: Permette di caricare o creare sessioni di combattimento, aggiungere combattanti, ordinare per Iniziativa, modificare HP in tempo reale, avanzare nei turni di gioco e consultare note segrete.
- **Supporto Multi-Sistema**: Supporta configurazioni per D&D 5e e altri sistemi (es. `powerfail`).
- **Render Stat Block in AJAX**: I dettagli delle creature vengono inviati al server e convertiti al volo tramite `MarkdownPreprocessor::toHtml()`.

### Libreria Personaggi (`DmCharacter`)
Permette di salvare schede di personaggi e mostri suddivisi in tre categorie:
1. `player`: Personaggi giocanti.
2. `template`: Mostri e PNG riutilizzabili disponibili come modello.
3. `group`: Gruppi preconfigurati di combattanti.

### Player Live View (`/dm/player/{share_code}`)
- Ogni sessione di combattimento genera un codice di condivisione univoco (`share_code`).
- I giocatori possono accedere alla rotta pubblica `/dm/player/{share_code}` per seguire l'ordine di iniziativa, il turno corrente e lo stato di salute generale dei combattanti su uno schermo condiviso.

---

## 👤 Sistema di Autenticazione, Ruoli e Profilo Utente

Il sistema si basa su Laravel Auth con un modello esteso [`App\Models\User`](file:///c:/Users/RickyMandich/PROJECT/Phandalverse/phandalverse/app/Models/User.php):

| Ruolo / Flag | Metodo Helper | Permessi e Funzionalità |
| :--- | :--- | :--- |
| **Guest / Visitatore** | - | Consultazione note pubbliche del Vault per la campagna aperta, vista Player View, invio segnalazioni. |
| **User (Registrato)** | `Auth::check()` | Profilo utente, scelta campagna predefinita (`default_campaign_id`), richiesta di diventare Master (`master_request`). Accesso alle campagne assegnate in `campaign_user`. |
| **Master Utils** | `isMasterUtils()` | Accesso allo schermo DM Screen (`/dm`), gestione iniziative e personaggi. |
| **Master Completo** | `isMaster()` | Accesso totale a **tutte le campagne** e a tutte le note `#dm`, blocchi `#startMaster`, note segrete nelle sessioni. |
| **Admin** | `isAdmin()` | Accesso completo al pannello `/admin` (gestione campagne, utenti, gruppi di accesso, log, console SQL, errori, statistiche). |

---

## ✈️ Coda Email Asincrona (Fire-and-Forget su Hosting Limiti)

Sugli hosting condivisi (come Altervista), non è possibile eseguire il comando CLI `php artisan queue:work` in background. Per superare questa limitazione senza rallentare l'esperienza dell'utente, Phandalverse adotta un sistema di **Coda Email Asincrona Fire-and-Forget**:

```
[Azione Utente] 
       │
       ▼
[EmailQueueService::queue()] ──► Inserisce Job nella tabella 'jobs' del DB
       │
       ├─► [Trigger 1: JobController::fireAndForgetGet()] 
       │      Apre un socket/cURL non bloccante verso /job/ProcessEmailQueue?token=...
       │      senza attendere la risposta HTTP.
       │
       └─► [Trigger 2: ProcessEmailQueueMiddleware]
              Processa 1 job al termine di una qualsiasi richiesta HTTP normale come fallback.
```

- **`EmailQueueService`**: Gestisce l'accodamento delle mail (es. verifica account, notifiche admin, reset password).
- **`JobController@processEmailQueue`**: Endpoint protetto da `JOB_TOKEN` per l'elaborazione dei job in coda con rate limiting.

---

## 🤖 Integrazione Telegram Bot & Notifiche Multi-Campagna

Il bot Telegram di Phandalverse ([`TelegramBotController`](file:///c:/Users/RickyMandich/PROJECT/Phandalverse/phandalverse/app/Http/Controllers/TelegramBotController.php) e [`TelegramService`](file:///c:/Users/RickyMandich/PROJECT/Phandalverse/phandalverse/app/Services/TelegramService.php)) permette di interagire con il Vault e ricevere notifiche di sistema isolate per campagna.

### Comandi Telegram Supportati:
- `/start`: Messaggio di benvenuto e lista comandi.
- `/subscribe`: Se sono presenti più campagne, mostra una tastiera inline per scegliere a quale/i campagna/e iscrivere la chat o il topic.
- `/unsubscribe`: Permette di disattivare le notifiche per una specifica campagna o per tutte.
- `/search <query>`: Cerca note tra tutte le campagne e restituisce pulsanti inline per la lettura sul sito o in-app.
- `/view <slug>`: Renderizza il testo di una nota direttamente dentro la chat Telegram.

### Notifiche Broadcast per Campagna (`notifyUpdate`):
Quando viene rilasciata una nuova versione del Vault per una campagna, lo script di upload/webhook chiama:
```
GET /api/notify-update?token={JOB_TOKEN}&campaign={folder_name}
```
Il bot invia la notifica del changelog esclusivamente agli iscritti della campagna specificata.

---

## 🛠️ Pannello di Amministrazione (`/admin`)

Accessibile solo agli utenti con flag `admin = 1`:

- **Gestione Campagne (`/admin/campaigns`)**: Creazione, modifica di nome visualizzato, cartella Vault/branch, ordine di priorità e assegnazione utenti con accesso.
- **Gestione Gruppi di Accesso (`/admin/access-groups`)**: Creazione e modifica di gruppi gerarchici legati a una specifica campagna.
- **Gestione Utenti (`/admin/users`)**: Assegnazione ruoli (Admin, Master, MasterUtils), campagne abilitate, campagna predefinita e gruppi di accesso.
- **Gestione Errori (`/admin/errors`)**: Tracciamento eccezioni di runtime con stato (`new`, `in_progress`, `resolved`, `ignored`).
- **Web Console Database (`/admin/database`)**: Interfaccia per eseguire query SQL arbitrarie (SELECT, UPDATE, INSERT, ALTER TABLE) direttamente sul database del server.
- **Statistiche & Export (`/admin/statistics`)**: Grafici temporali sulle visite (Chart.js), utenti unici, tempi di risposta ed export CSV/JSON.
- **Log Monitor (`/admin/logs`)**: Visualizzatore dei log applicativi.

---

## 🪵 Sistema di Logging Personalizzato (`CustomLogger`)

Per evitare log monolitici e facilitare il debug su specifici moduli, [`App\Services\CustomLogger`](file:///c:/Users/RickyMandich/PROJECT/Phandalverse/phandalverse/app/Services/CustomLogger.php) suddivide i log in sotto-cartelle dedicate in `storage/logs/`:

- **`storage/logs/notes/`**: Contiene un log per ciascuna nota letta (`YYYY-MM-DD_HH-MM-SS_nome-nota.log`).
- **`storage/logs/graph/`**: Registra la generazione e l'indicizzazione dei nodi del grafo interattivo per campagna.
- **`storage/logs/screen/`**: Traccia le azioni effettuate sullo schermo Master / Player View.
- **`storage/logs/telegram/`**: Registra i webhook ricevuti e le risposte inviate al Bot Telegram.

---

## 🚀 Workflow di Sviluppo, Deployment e Regole Server

Per lavorare correttamente sul progetto è necessario attenersi alle seguenti regole operative:

### 1. Ambiente di Produzione Diretto & Script FTP (`bash/`)
Il lavoro viene solitamente svolto e testato direttamente nell'ambiente di produzione tramite il caricamento dei file modificati via FTP server (`Phandalverse.altervista.org`).

Nella cartella `bash/` sono presenti gli script di utilità:
- **`bash/cmt.sh`**: Effettua `git add .`, compone il messaggio di commit includendo la versione letta dal `.env` ed esegue il `git push`.
- **`bash/onlyFtpOfLastCmt.sh`**: Legge i file modificati nell'ultimo commit Git e li carica via FTP sul server di produzione.
- **`bash/onlyFtpOfCmtById.sh`**: Carica via FTP i file di un commit specifico tramite il suo Hash ID.
- **`bash/pull.sh`**: Esegue il pull delle modifiche remote.

> ⚠️ **REGOLA FONDAMENTALE SULL'USO DEGLI SCRIPT**:
> **NON eseguire mai lo script `bash/all.sh` in automatico.** Questo script incrementa la versione ed esegue il commit/push globale. L'amministratore preferisce eseguire la revisione ed il caricamento manuale o tramite FileZilla / script specifici.

### 2. Regola Obbligatoria per le Migrazioni del Database
Poiché sul server di produzione **non è possibile eseguire comandi da terminale** (es. `php artisan migrate`), per **ogni nuova migrazione creata** occorre inserire nei commenti del file la query SQL equivalente per MySQL, in modo che possa essere eseguita direttamente nella Console SQL dell'Admin (`/admin/database`) o su phpMyAdmin.

*Esempio per il modulo Multi-Campagna (da eseguire su produzione):*
```sql
-- 1. Tabella campaigns
CREATE TABLE IF NOT EXISTS `campaigns` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `folder_name` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(255) NOT NULL,
  `order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaigns_folder_name_unique` (`folder_name`),
  UNIQUE KEY `campaigns_order_unique` (`order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `campaigns` (`id`, `folder_name`, `display_name`, `order`, `created_at`, `updated_at`)
VALUES (1, 'newCampaign', 'Phandalin', 10, NOW(), NOW())
ON DUPLICATE KEY UPDATE `display_name` = VALUES(`display_name`);

-- 2. Tabella pivot campaign_user
CREATE TABLE IF NOT EXISTS `campaign_user` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaign_user_unique` (`campaign_id`, `user_id`),
  CONSTRAINT `campaign_user_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `campaign_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `campaign_user` (`campaign_id`, `user_id`, `created_at`, `updated_at`)
SELECT 1, `id`, NOW(), NOW() FROM `users`;

-- 3. Aggiunta default_campaign_id su users
ALTER TABLE `users` ADD COLUMN `default_campaign_id` BIGINT UNSIGNED NULL AFTER `collapseEmbed`;
ALTER TABLE `users` ADD CONSTRAINT `users_default_campaign_id_foreign` FOREIGN KEY (`default_campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL;
UPDATE `users` SET `default_campaign_id` = 1 WHERE `default_campaign_id` IS NULL;

-- 4. Aggiunta campaign_id su access_groups
ALTER TABLE `access_groups` ADD COLUMN `campaign_id` BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`;
ALTER TABLE `access_groups` ADD CONSTRAINT `access_groups_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE;
ALTER TABLE `access_groups` DROP INDEX `access_groups_slug_unique`;
ALTER TABLE `access_groups` ADD UNIQUE KEY `access_groups_campaign_id_slug_unique` (`campaign_id`, `slug`);

-- 5. Aggiunta campaign_id su telegram_subscribers
ALTER TABLE `telegram_subscribers` ADD COLUMN `campaign_id` BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`;
ALTER TABLE `telegram_subscribers` ADD CONSTRAINT `telegram_subscribers_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE;
```

### 3. Gestione della Cartella `/Vault`
La cartella `/Vault` contiene i diversi Vault Markdown e le risorse multimediali ed **esiste esclusivamente sul server di produzione** (non viene tracciata integralmente nel repository Git per ragioni di peso e riservatezza).
