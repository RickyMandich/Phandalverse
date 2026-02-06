# Architettura Tecnica: Schermo del Master e Visuale Giocatore

Questo documento descrive le tecnologie, l'architettura e le modalità di sincronizzazione utilizzate per far funzionare lo **Schermo del Master (DM Screen)** e la **Visuale Giocatore (Player View)** nel progetto Phandalverse.

---

## 🛠️ Stack Tecnologico

### Backend (Core)
- **Laravel 12.0**: Framework principale per la gestione di rotte, database, autenticazione e API.
- **PHP 8.2+**: Linguaggio lato server.
- **MySQL/MariaDB**: Gestione della persistenza dei dati (Sessioni di combattimento e Personaggi).
- **MarkdownPreprocessor**: Servizio custom per il parsing dinamico delle statistiche e delle note (stat-blocks) da Markdown a HTML.

### Frontend (UI & Logica Client)
- **Alpine.js**: Framework JavaScript leggero utilizzato per gestire lo stato reattivo di entrambe le pagine.
- **Bootstrap 5.3.8**: Framework CSS per il layout, lo stile e i componenti UI (Modal, Dropdown).
- **Sass (SCSS)**: Gestione degli stili personalizzati.
- **Vite 7.0.7**: Build tool per la compilazione degli asset.
- **Fetch API**: Utilizzata per le chiamate asincrone (AJAX) tra frontend e backend.

---

## 🏗️ Architettura e Relazioni

Il sistema si basa su un modello **Client-Server** con sincronizzazione basata su **Polling HTTP**.

### 1. Lo Schermo del Master (`/dm`)
Gestito dal controller `DmController` e dalla vista `dm.screen.blade.php`.
- **Stato Locale**: La logica è incapsulata in un componente Alpine.js (`dmScreen`). Utilizza un sistema a **Coda (Queue)** circular: il personaggio attivo è sempre all'indice 0. Quando il Master preme "Next Turn", il primo elemento viene rimosso e inserito in coda. I mostri morti (non player) vengono saltati automaticamente.
- **Persistenza**: 
    - Lo stato viene salvato interamente in formato JSON nel database. Include l'array `combatants` ruotato, il `round` attuale e il `roundStartId`.
- **Auto-salvataggio**: Un timer di backup salva lo stato ogni **15 secondi** se non sono state effettuate azioni manuali.

### 2. La Visuale Giocatore (`/dm/player/{share_code}`)
Gestita dal controller `DmController` e dalla vista `dm.player.blade.php`.
- **Sincronizzazione (Polling)**: Non avendo un server WebSocket attivo (es. Pusher), la visuale giocatore effettua una richiesta `GET` all'API pubblica `/dm/api/public/sessions/{share_code}` ogni **300ms**.
- **Rendering**: Il componente Alpine.js (`playerView`) riceve il JSON dello stato, lo parsa e aggiorna l'interfaccia in tempo reale.
- **Sicurezza**: Il backend filtra i dati prima di inviarli ai giocatori (es. rimuove le note segrete del master e limita le informazioni visibili).

---

## 📂 File Chiave

| File | Descrizione |
| :--- | :--- |
| `app/Http/Controllers/DmController.php` | Gestisce le API di salvataggio/caricamento e il rendering delle viste. |
| `resources/views/dm/screen.blade.php` | Struttura HTML dello schermo del Master. |
| `resources/views/dm/player.blade.php` | Struttura HTML della visuale pubblica dei giocatori. |
| `public/js/dm-screen.js` | Logica JavaScript (Alpine.js) dello schermo del Master. |
| `app/Models/DmSession.php` | Modello Eloquent per le sessioni (include la generazione del `share_code`). |
| `app/Models/DmCharacter.php` | Modello Eloquent per i template di mostri e personaggi. |

---

## ⚙️ Versioni Riferimento

- **Laravel**: `^12.0`
- **PHP**: `^8.2`
- **Alpine.js**: caricato via CDN (ultimo build stabile)
- **Bootstrap**: `^5.3.8`
- **Vite**: `^7.0.7`
- **Tailwind CSS**: `^4.0.0` (presente nel progetto, ma l'interfaccia DM usa prevalentemente Bootstrap).

---

## 🔄 Flusso di Sincronizzazione

1. **Il Master** modifica un valore (es. toglie 10 HP a un Orco).
2. **Alpine.js** aggiorna la UI locale e chiama `saveSession()`.
3. **Fetch API** invia il nuovo stato JSON al database Laravel.
4. **La Visuale Giocatore**, tramite il polling a 300ms, rileva il cambiamento nel database.
5. **Alpine.js (Giocatore)** aggiorna la vista mostrando l'aggiornamento quasi istantaneamente.

---

## 🎲 Dettagli Logica Combat (Queue System)

### Gestione Iniziativa e Rotazione
Quando vengono aggiunti nuovi personaggi o si modifica un'iniziativa, la funzione `sortCombat()` esegue i seguenti passi:
1. **Salvataggio Actor**: Identifica l'ID del personaggio attualmente attivo (indice 0).
2. **Ordinamento**: Ordina tutti i personaggi per iniziativa decrescente (e modificatore di Destrezza in caso di pareggio).
3. **Rotazione (Rotation)**: "Ruota" l'array ordinato in modo che il personaggio attivo torni in prima posizione, preservando la sequenza circolare del combattimento. Questo evita che i nuovi arrivati "saltino" il turno a chi sta giocando.

### Tracciamento dei Round
Poiché l'indice del turno è fisso a 0, il passaggio del round è gestito tramite un **Marker**:
- All'inizio del combattimento, il primo personaggio riceve un marker nascosto (`roundStartId`).
- Ogni volta che la coda ruota, se il personaggio in testa è quello con il `roundStartId`, il contatore `round` viene incrementato.
- Se il personaggio che detiene il marker viene eliminato, il marker viene passato automaticamente al personaggio successivo nella lista.
