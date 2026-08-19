# 🌌 Phandalverse - Documentazione Tecnica & Guida per lo Sviluppatore

Benvenuto nel repository di **Phandalverse**, la piattaforma web integrata e companion app per la gestione di campagne D&D / RPG, worldbuilding e strumenti di gioco.

Questa documentazione fornisce una panoramica completa sull'architettura, le scelte di design, i moduli principali e le modalità di gestione delle risorse, permettendo a qualsiasi sviluppatore di comprendere ed entrare direttamente nello sviluppo del progetto senza necessità di ulteriori spiegazioni.

---

## 📋 Indice
1. [🌌 Introduzione e Visione del Progetto](#-introduzione-e-visione-del-progetto)
2. [🏛️ Architettura del Sistema & Struttura del Codice](#%EF%B8%8F-architettura-del-sistema--struttura-del-codice)
3. [📚 Gestione del Vault (Obsidian Integration Engine)](#-gestione-del-vault-obsidian-integration-engine)
   - [Normalizzazione dei Percorsi e `map.json`](#normalizzazione-dei-percorsi-e-mapjson)
   - [Engine Markdown (`MarkdownPreprocessor`)](#engine-markdown-markdownpreprocessor)
   - [Permessi di Lettura e Filtraggio Blocchi (`#dm`, `#startMaster`)](#permessi-di-lettura-e-filtraggio-blocchi-dm-startmaster)
   - [Pannello ad Albero e Grafo Interattivo](#pannello-ad-albero-e-grafo-interattivo)
   - [Ricerca, API Raw e Embed Immagini](#ricerca-api-raw-e-embed-immagini)
4. [⚔️ DM Screen & Gestione Sessioni D&D (`/dm`)](#%EF%B8%8F-dm-screen--gestione-sessioni-dd-dm)
   - [Iniziative, Turni e Stat Block](#iniziative-turni-e-stat-block)
   - [Libreria Personaggi (`DmCharacter`)](#libreria-personaggi-dmcharacter)
   - [Player Live View (`/dm/player/{share_code}`)](#player-live-view-dmplayershare_code)
5. [👤 Sistema di Autenticazione e Ruoli Utente](#-sistema-di-autenticazione-e-ruoli-utente)
6. [✈️ Coda Email Asincrona (Fire-and-Forget su Hosting Limiti)](#%EF%B8%8F-coda-email-asincrona-fire-and-forget-su-hosting-limiti)
7. [🤖 Integrazione Telegram Bot & Notifiche Broadcast](#-integrazione-telegram-bot--notifiche-broadcast)
8. [🛠️ Pannello di Amministrazione (`/admin`)](#%EF%B8%8F-pannello-di-amministrazione-admin)
9. [🪵 Sistema di Logging Personalizzato (`CustomLogger`)](#-sistema-di-logging-personalizzato-customlogger)
10. [🚀 Workflow di Sviluppo, Deployment e Regole Server](#-workflow-di-sviluppo-deployment-e-regole-server)

---

## 🌌 Introduzione e Visione del Progetto

Phandalverse nasce con un'idea di fondo chiara: **trasformare un Vault di Obsidian (file Markdown) in una piattaforma web dinamica, interattiva e multi-utente**, affiancata da strumenti di ausilio per il Dungeon Master (DM Screen) e per i giocatori (Player View in tempo reale).

### Concetti Chiave:
- **Obsidian come Single Source of Truth**: L'enciclopedia del mondo di gioco è scritta in Markdown e posizionata sul server nella cartella `/Vault`. L'applicazione web la trasforma in pagine HTML consultabili con supporto a Wikilink, Graph View, visualizzazione immagini e permessi differenziati.
- **Supporto ad Hosting Condivisi (Altervista)**: Il sistema è progettato per funzionare senza demoni CLI sempre attivi (`php artisan queue:work` o SMTP dedicati). La coda email, le notifiche e la gestione log utilizzano meccanismi *fire-and-forget* via socket/cURL interni e middleware parassita.
- **Workflow Diretto in Produzione via FTP**: Gli aggiornamenti sul server di produzione vengono distribuiti tramite script FTP automatizzati basati su Git (`bash/`).

---

## 🏛️ Architettura del Sistema & Struttura del Codice

Il progetto è costruito sul framework **Laravel 12** con frontend basato su **Blade**, **Bootstrap 5**, **Sass**, **Vite** e librerie JavaScript specializzate (es. **D3.js / Force Graph** per il grafo delle note).

```
phandalverse/
├── app/
│   ├── Helpers/
│   │   └── VaultHelper.php          # Risoluzione map.json, nomi originali, ricerca note
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AdminController.php  # Gestione utenti, errori, SQL web console, stats
│   │   │   ├── DmController.php     # Logica DM Screen, sessioni combat, player view
│   │   │   ├── VaultController.php  # Rendering note, albero, grafo interattivo, API raw
│   │   │   ├── TelegramBotController.php # Webhook telegram, comandi, notifiche
│   │   │   ├── JobController.php    # Processore asincrono della coda email
│   │   │   ├── ReportController.php # Segnalazioni utenti
│   │   │   └── ChangelogController.php # Visualizzazione versioni del vault
│   │   └── Middleware/
│   ├── Jobs/                        # Job per invio mail in coda
│   ├── Models/
│   │   ├── User.php                 # Modello Utente con ruoli (Admin, Master, MasterUtils) e gruppi di accesso
│   │   ├── AccessGroup.php          # Gruppi di accesso gerarchici al Vault (slug, colore, parent_id)
│   │   ├── DmSession.php            # Sessione di combattimento (dati JSON + share_code)
│   │   ├── DmCharacter.php          # Template e PG/PNG salvati
│   │   ├── SystemError.php          # Log errori di sistema per admin
│   │   ├── SystemSetting.php        # Impostazioni di sistema (es. default vault view)
│   │   ├── Statistic.php            # Statistiche visite e tempo di risposta
│   │   └── TelegramSubscriber.php   # Iscritti alle notifiche telegram
│   └── Services/
│       ├── AccessControlService.php # Controllo accessi unificato note/blocchi e gerarchia gruppi
│       ├── MarkdownPreprocessor.php # Engine di parsing Markdown (Wikilink, Embed, AccessBlock, Statblock)
│       ├── TelegramService.php      # Client API Telegram (invio messaggi, broadcast, notifiche)
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
│       ├── admin/                   # Dashboard amministrativa (utenti, gruppi di accesso, errori, db, stats)
│       ├── dm/                      # Schermate DM Screen, Manage e Player View
│       ├── vault/                   # Note, albero side-bar, grafo, ricerca, changelog
│       └── layouts/                 # Master layout dell'applicazione
├── routes/
│   └── web.php                      # Tutte le rotte web, API pubbliche e webhook
└── Vault/                           # Cartella del Vault (PRESENTE SOLO SUL SERVER DI PRODUZIONE)
```

---

## 📚 Gestione del Vault (Obsidian Integration Engine)

Il Vault rappresenta l'enciclopedia dell'universo narrativo. Si trova nella directory `/Vault` della root del progetto sul server.

### Normalizzazione dei Percorsi e `map.json`
I file e le cartelle nel Vault reale su disco sono normalizzati (nomi minuscoli, trattini al posto degli spazi, senza caratteri speciali) per garantire la massima compatibilità tra file system (Windows/Linux) e URL puliti.

- **`Vault/.normalize/map.json`**: Mantiene l'albero di associazione tra i percorsi normalizzati e i titoli/nomi originali (con maiuscole, accenti e spazi).
- **`App\Helpers\VaultHelper`**:
  - `getOriginalName($normalizedPath)`: Recupera il nome formattato originale partendo dal path normalizzato.
  - `getOriginalDirectoryName($dirPath)`: Restituisce il nome reale di una cartella.
  - `searchNotes($query)`: Esegue la ricerca ricorsiva all'interno di `map.json`.

### Engine Markdown (`MarkdownPreprocessor`) & Access Control (`AccessControlService`)
Il servizio [`App\Services\MarkdownPreprocessor`](file:///c:/Users/RickyMandich/PROJECT/Phandalverse/phandalverse/app/Services/MarkdownPreprocessor.php) estende `League\CommonMark` e implementa la conversione da Markdown ad HTML avanzato, integrato con [`App\Services\AccessControlService`](file:///c:/Users/RickyMandich/PROJECT/Phandalverse/phandalverse/app/Services/AccessControlService.php):

1. **Wikilink (`[[Nome Nota|Alias]]`)**:
   - Convertiti in tag `<a href="/vault/path-normalizzato" class="wikilink">Alias</a>`.
   - Se la nota target non esiste nell'indice o l'utente non ha i permessi per vederla, il wikilink viene renderizzato come semplice testo senza link.
2. **Note Embed (`![[Nome Nota]]` o `![[Nome Nota#Sezione]]`)**:
   - Carica e renderizza ricorsivamente la nota o sezione specificata dentro un box pieghevole (`.embed-note`), gestendo la profondità massima (`$maxEmbedDepth = 3`) per evitare loop infiniti.
   - **Verifica permessi ricorsiva**: ogni nota embeddata riesegue il check di visibilità in isolamento per sé stessa.
3. **Image Embed (`![[immagine.png]]` o `![[cartella/immagine.jpg]]`)**:
   - Cerca l'immagine nel Vault e genera il tag `<img src="/vault/path-encodato" class="wikilink-image">`.
4. **Obsidian Callouts & Stat Blocks**:
   - Converte i tag `#tag` in badge stilizzati (`<span class="obsidian-tag">`).
   - Trasforma le citazioni `> ...` nei tipici **Stat Block** in stile D&D.
5. **Numeri Romani**:
   - Renderizza le sequenze `R|IX|` nel formato stilizzato per le ere/capitoli.

### Permessi di Lettura e Gruppi di Accesso (`#access-`, `#dm`, `#startAccess-`, `#startMaster`)
Il Vault gestisce un sistema granulare di visibilità parametrizzato su **Gruppi di Accesso** (`access_groups`):
- **Note intere riservate (`#access-gruppo1_gruppo2` o `#dm`)**:
  - Inserito all'inizio o nel corpo della nota. Più gruppi possono essere specificati separati da underscore `_` (logica OR).
  - Gli slug dei gruppi sono in formato **camelCase** (es. `cavalieriDelDrago`, `artefici`) e non possono contenere trattini `-` o underscore `_`.
  - Se l'utente appartiene a uno dei gruppi indicati (o a un loro gruppo discendente) oppure è Master, la nota è visibile. Altrimenti il server restituisce `404 Not Found` (invisibilità totale, esclusa anche dall'albero, dal grafo e dalla ricerca).
  - Il tag `#dm` agisce come gruppo implicito riservato esclusivamente ai Master.
- **Blocchi parziali riservati (`#startAccess-gruppo1_gruppo2 ... #endAccess` e `#startMaster ... #endMaster`)**:
  - **Per chi ha accesso**: il contenuto interno viene renderizzato con un bordo colorato calcolato dalla gerarchia (`resolveBlockColor`) e con badge personalizzati per l'utente (`computeBadgeGroups`).
  - **Per chi non ha accesso**: il blocco viene interamente rimosso prima del rendering HTML.
- **Ereditarietà Gerarchica dei Gruppi**:
  - Chi appartiene a un gruppo *figlio* (es. `bibliotecari`) eredita l'accesso ai contenuti del gruppo *padre* (es. `artefici`). Il padre non vede i contenuti del figlio.
  - Il Master bypassa ogni restrizione e vede sempre tutte le note e i blocchi con badge dedicati.

### Pannello ad Albero e Grafo Interattivo
- **Vista ad Albero (`VaultController@buildFileTree`)**: Costruisce la navigazione laterale analizzando `map.json` ed escludendo tutte le note non visibili per l'utente corrente tramite `AccessControlService::noteIsVisibleTo()`.
- **Grafo Interattivo (`VaultController@buildGraphData` e `graph.blade.php`)**:
  - Analizza tutti i file `.md` visibili all'utente e ne estrae le connessioni (wikilink ed embed).
  - Legge la configurazione estetica direttamente da `Vault/.obsidian/graph-config.json` o `graph.json` (forze di repulsione, distanza link, gruppi colore per tag come `#universo`, `#città`, `#pg`, `#png`, `#saga`, `#evento`).

### Ricerca, API Raw e Embed Immagini
- **`/vault/search?q=...`**: Ricerca veloce tra le note con reindirizzamento automatico se c'è un unico risultato e filtraggio permessi.
- **`/api/vault/{note}`**: Restituisce il file Markdown grezzo originale (con i blocchi riservati filtrati in base all'utente) scaricabile come file `.md`.
- **Risoluzione Immagini (`/vault/...`)**: Il controller `VaultController` intercetta le richieste di file binari (PNG, JPG, WEBP, ecc.) presenti nel Vault e li serve direttamente con il corretto Content-Type.

---

## ⚔️ DM Screen & Gestione Sessioni D&D (`/dm`)

Il modulo DM Screen ([`App\Http\Controllers\DmController`](file:///c:/Users/RickyMandich/PROJECT/Phandalverse/phandalverse/app/Http/Controllers/DmController.php)) fornisce una console completa per la gestione dei combattimenti e dei turni di gioco.

### Iniziative, Turni e Stat Block
- **Console Master (`/dm`)**: Permette di caricare o creare sessioni di combattimento, aggiungere combattanti, ordinare per Iniziativa, modificare HP in tempo reale, avanzare nei turni di gioco (gestendo anche la gestione dei caduti/morti) e consultare note segrete.
- **Supporto Multi-Sistema**: Supporta configurazioni per D&D 5e e altri sistemi (es. `powerfail`).
- **Render Stat Block in AJAX**: I dettagli delle creature vengono inviati al server e convertiti al volo tramite `MarkdownPreprocessor::toHtml()`.

### Libreria Personaggi (`DmCharacter`)
Permette di salvare schede di personaggi e mostri suddivisi in tre categorie:
1. `player`: Personaggi giocanti.
2. `template`: Mostri e PNG riutilizzabili disponibili come modello.
3. `group`: Gruppi preconfigurati di combattanti.

Gli utenti con ruolo `MasterUtils` (ma non Master completo) hanno una visione con note di combattimento oscurate (`[ACCESSO LIMITATO]`).

### Player Live View (`/dm/player/{share_code}`)
- Ogni sessione di combattimento genera un codice di condivisione univoco (`share_code`).
- I giocatori possono accedere alla rotta pubblica `/dm/player/{share_code}` per seguire l'ordine di iniziativa, il turno corrente e lo stato di salute generale dei combattanti su uno schermo condiviso.
- **Segretezza garantita**: Le note personali del Master e i dettagli riservati degli stat-block non vengono mai inviati all'API pubblica della Player View (`/dm/api/public/sessions/{share_code}`).

---

## 👤 Sistema di Autenticazione e Ruoli Utente

Il sistema si basa su Laravel Auth con un modello esteso [`App\Models\User`](file:///c:/Users/RickyMandich/PROJECT/Phandalverse/phandalverse/app/Models/User.php):

| Ruolo / Flag | Metodo Helper | Permessi e Funzionalità |
| :--- | :--- | :--- |
| **Guest / Visitatore** | - | Consultazione note pubbliche del Vault, vista Player View, invio segnalazioni. |
| **User (Registrato)** | Auth::check() | Profilo utente, richiesta di diventare Master (`master_request`). |
| **Master Utils** | `isMasterUtils()` | Accesso allo schermo DM Screen (`/dm`), gestione iniziative e personaggi. |
| **Master Completo** | `isMaster()` | Accesso totale al Vault: visualizzazione note `#dm`, blocchi `#startMaster`, note segrete nelle sessioni. |
| **Admin** | `isAdmin()` | Accesso completo al pannello `/admin` (gestione utenti, log, console SQL, errori, statistiche). |

### Mutatore Password Personalizzato
Per garantire la compatibilità con eventuali importazioni o sistemi legacy, il mutatore `setPasswordAttribute` in `User.php` gestisce sia le classiche password bcrypt (`$2y$`), sia eventuali hash custom con prefisso `V2:`, evitando di ri-hashare password già elaborate.

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
- **`JobController@processEmailQueue`**: Endpoint protetto da `JOB_TOKEN` per l'elaborazione dei job in coda con rate limiting per evitare blocchi del server di posta.

---

## 🤖 Integrazione Telegram Bot & Notifiche Broadcast

Il bot Telegram di Phandalverse ([`TelegramBotController`](file:///c:/Users/RickyMandich/PROJECT/Phandalverse/phandalverse/app/Http/Controllers/TelegramBotController.php) e [`TelegramService`](file:///c:/Users/RickyMandich/PROJECT/Phandalverse/phandalverse/app/Services/TelegramService.php)) permette di interagire con il Vault e ricevere notifiche di sistema.

### Comandi Telegram Supportati:
- `/start`: Messaggio di benvenuto e lista comandi (supporta Deep Linking per visualizzare note).
- `/subscribe`: Iscrive la chat o lo specifico **Topic di un gruppo** alle notifiche di aggiornamento.
- `/unsubscribe`: Rimuove l'iscrizione.
- `/search <query>`: Cerca note nel Vault e restituisce pulsanti inline per la lettura sul sito o in-app.
- `/view <slug>`: Renderizza il testo di una nota direttamente dentro la chat Telegram (con formattazione pulita e tabelle convertite).

### Notifiche Automatiche Invitate dal Sistema:
1. **Errore Critico di Sistema (`notifyError`)**: Invia immediatamente un report HTML del crash all'Admin.
2. **Nuova Segnalazione Utente (`notifyNewReport`)**: Avvisa l'admin quando viene inviato un report di bug/contenuto.
3. **Nuovo Utente / Cambio Password**: Notifiche di sicurezza inviate al canale Admin.
4. **Aggiornamento Vault (`notifyUpdate` / Broadcast)**: Notifica massiva inviata a tutte le chat/topic iscritti in `telegram_subscribers` quando viene rilasciata una nuova versione del Vault.

---

## 🛠️ Pannello di Amministrazione (`/admin`)

Accessibile solo agli utenti con flag `admin = 1`. Fornisce gli strumenti per il controllo e la manutenzione della piattaforma:

- **Gestione Errori (`/admin/errors`)**: Traccia le eccezioni catturate dal sistema ([`SystemError`](file:///c:/Users/RickyMandich/PROJECT/Phandalverse/phandalverse/app/Models/SystemError.php)), permettendo di cambiarne lo stato (`new`, `in_progress`, `resolved`, `ignored`) direttamente da web o via email quick-action.
- **Gestione Utenti (`/admin/users`)**: Creazione, modifica ruoli (Admin, Master, MasterUtils), approvazione o rifiuto delle richieste Master e reset password.
- **Web Console Database (`/admin/database`)**: Interfaccia per eseguire query SQL arbitrarie (SELECT, UPDATE, INSERT, ALTER TABLE) direttamente sul database del server. Essenziale dato che l'accesso SSH da terminale non è presente su produzione.
- **Statistiche & Export (`/admin/statistics`)**: Grafici temporali sulle visite (Chart.js), utenti unici, tempi medi di risposta e funzione di esportazione raw in CSV e JSON.
- **Log Monitor (`/admin/logs`)**: Visualizzatore dei log applicativi.
- **Test Mailer (`/admin/test-mail`)**: Strumento per verificare il corretto funzionamento della coda mail.

---

## 🪵 Sistema di Logging Personalizzato (`CustomLogger`)

Per evitare log monolitici e facilitare il debug su specifici moduli, [`App\Services\CustomLogger`](file:///c:/Users/RickyMandich/PROJECT/Phandalverse/phandalverse/app/Services/CustomLogger.php) suddivide i log in sotto-cartelle dedicate in `storage/logs/`:

- **`storage/logs/notes/`**: Contiene un log per ciascuna nota letta (`YYYY-MM-DD_HH-MM-SS_nome-nota.log`).
- **`storage/logs/graph/`**: Registra la generazione e l'indicizzazione dei nodi del grafo interattivo.
- **`storage/logs/screen/`**: Traccia le azioni effettuate sullo schermo Master / Player View.
- **`storage/logs/telegram/`**: Registra i webhook ricevuti e le risposte inviate al Bot Telegram.

---

## 🚀 Workflow di Sviluppo, Deployment e Regole Server

Per lavorare correttamente sul progetto è necessario attenersi alle seguenti regole operative:

### 1. Ambiente di Produzione Diretto & Script FTP (`bash/`)
Il lavoro viene solitamente svolto e testato direttamente nell'ambiente di produzione tramite il caricamento dei file modificati via FTP server (`Phandalverse.altervista.org`).

Nella cartella `bash/` sono presenti gli script di utilità:
- **`bash/cmt.sh`**: Effettua `git add .`, compone il messaggio di commit includendo la versione letta dal `.env` ed esegue il `git push`.
- **`bash/onlyFtpOfLastCmt.sh`**: Legge i file modificati nell'ultimo commit Git e li carica via FTP sul server di produzione (creando cartelle se mancanti o rimuovendo file eliminati tramite `DELE`).
- **`bash/onlyFtpOfCmtById.sh`**: Carica via FTP i file di un commit specifico tramite il suo Hash ID.
- **`bash/pull.sh`**: Esegue il pull delle modifiche remote.

> ⚠️ **REGOLA FONDAMENTALE SULL'USO DEGLI SCRIPT**:
> **NON eseguire mai lo script `bash/all.sh` in automatico.** Questo script incrementa la versione ed esegue il commit/push globale. L'amministratore preferisce eseguire la revisione ed il caricamento manuale o tramite FileZilla / script specifici.

### 2. Regola Obbligatoria per le Migrazioni del Database
Poiché sul server di produzione **non è possibile eseguire comandi da terminale** (es. `php artisan migrate`), per **ogni nuova migrazione creata** occorre inserire nei commenti del file la query SQL equivalente per MySQL, in modo che possa essere eseguita direttamente nella Console SQL dell'Admin (`/admin/database`) o su phpMyAdmin.

*Esempio in una migrazione:*
```php
/* 
-- SCRIPT MYSQL PER SERVER DI PRODUZIONE:
ALTER TABLE `users` ADD COLUMN `master_utils` TINYINT(1) DEFAULT 0 AFTER `master`;
*/
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->boolean('master_utils')->default(false)->after('master');
    });
}
```

### 3. Gestione della Cartella `/Vault`
La cartella `/Vault` contiene l'intero archivio Markdown e le risorse multimediali ed **esiste esclusivamente sul server di produzione** (non viene tracciata integralmente nel repository Git per ragioni di peso e riservatezza).
