# Phandalverse Vault

Benvenuto nel repository di **Phandalverse Vault**, una Web Application personalizzata basata su **Laravel** progettata per gestire, visualizzare e navigare una complessa base di conoscenza in formato Markdown (Vault), con funzionalità specifiche per campagne di Giochi di Ruolo (GDR).

## 🌟 Funzionalità Principali

### 📖 Motore Markdown Avanzato
Il cuore del sistema è un **MarkdownPreprocessor** customizzato che estende le capacità standard:
-   **Wikilinks & Embeds**: Supporto nativo per `[[link]]` e `![[embed]]` stile Obsidian.
-   **Embed Ricorsivi**: Possibilità di includere intere note o sezioni di note dentro altre note.
-   **Gatekeeping dei Contenuti**: Sistema di permessi granulare.
    -   Tag `#dm` per note visibili solo al Master.
    -   Blocchi `#startMaster ... #endMaster` visibili solo al Master.
-   **Numeri Romani**: Rendering automatico per formattazione estetica.

### 🛡️ Gestione Utenti e Ruoli
-   **Ruoli**: Admin, Master, Utente Standard.
-   **Visibilità Dinamica**: I contenuti sensibili (spoiler, trame segrete) sono automaticamente filtrati dal backend prima di arrivare al frontend in base al ruolo dell'utente loggato.

### ⚙️ Sistema Customizzato
-   **Job e Queue senza Daemon**: Un sistema innovativo di "Self-calling Jobs" via HTTP per gestire code di email e task pesanti in ambienti hosting senza accesso a `supervisor` o demoni CLI.
-   **Monitoraggio Errori**: Dashboard admin per tracciare eccezioni e log, con integrazione notifiche (Email/Telegram).

## 🛠️ Stack Tecnologico

-   **Backend**: Laravel 12 (PHP 8.2+)
-   **Frontend**: Blade Templates + Bootstrap 5 (SCSS custom) + Vite
-   **Database**: MySQL/MariaDB
-   **Parsing**: League CommonMark con estensioni custom.

## 📂 Struttura Cartelle Chiave

-   `Vault/`: La directory (non versionata da questa repository ma inserita sul server, ha un versionamento indipendente che attualmente si trova in https://github.com/RickyMandich/PhandalverseVault) che contiene i file `.md`.
-   `app/Services/MarkdownPreprocessor.php`: Il motore logico di parsing.
-   `app/Http/Controllers/JobController.php`: Il gestore delle code asincrone via HTTP.

## 🚀 Installazione e Deploy

Il progetto è pensato per lavorare direttamente in produzione.

1.  Clonare il repository.
2.  `composer install`
3.  `npm install && npm run build`
4.  Configurare `.env` (Database, JOB_TOKEN, ecc.)
5.  Assicurarsi che la cartella `Vault` sia presente e popolata.

## ⚠️ Note Importanti

-   **Certificati Locali**: L'ambiente locale potrebbe richiedere configurazione per HTTPS dato che il ServiceProvider forza lo schema `https`.
-   **Server**: Richiede un server web standard (Apache/Nginx). Non usare `php artisan serve` per test di produzione del sistema di Job code.

---
*Progetto sviluppato da [RickyMandich](https://github.com/RickyMandich) per la gestione della campagna `Phandalverse`.*
