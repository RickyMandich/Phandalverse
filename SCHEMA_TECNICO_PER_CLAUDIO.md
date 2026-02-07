# Powerfail System 5.3

## Schermo del Master Digitale

### Preview testuale dell’interfaccia e del funzionamento

Questo documento descrive **come apparirà e funzionerà lo schermo del master**, concentrandosi su **interfaccia, pulsanti, automatismi e controlli manuali**, per verificare che il comportamento dello strumento sia coerente con le intenzioni del sistema.

Non introduce nuove regole e non modifica il bilanciamento:
descrive solo **come le regole già esistenti vengono supportate dall’interfaccia**.

---

## 1. Struttura generale della schermata

Lo schermo è una **pagina unica**, pensata per rimanere aperta durante tutta la sessione.

È divisa visivamente in tre aree:

1. **Barra di contesto** (in alto)
2. **Area attori in scena** (centro)
3. **Strumenti rapidi** (laterale o inferiore)

Non ci sono cambi di pagina durante il gioco.

---

## 2. Barra di contesto (in alto)

Questa area contiene:

* Nome della scena (campo modificabile)
* Campo di note del master (testo libero, privato)
* Stato della scena:

  * narrativa
  * combattimento

Cambiare stato **non forza nulla**, serve solo a:

* mostrare o nascondere alcuni strumenti
* aiutare il master a tenere ordine visivo

---

## 3. Area centrale – Attori in scena

Qui compaiono **solo entità rilevanti per il sistema**.

### 3.1 Personaggi giocanti

Ogni PG è rappresentato da una scheda visiva che mostra:

* caratteristiche
* abilità
* punti vita
* fatica
* stati attivi
* azioni disponibili

Su ogni valore numerico:

* è sempre possibile **cliccare e modificarlo manualmente**
* anche se normalmente viene gestito in automatico

Accanto a ogni abilità c’è un pulsante **“Tira”**.

---

### 3.2 Minion

I minion sono rappresentati con schede **molto compatte**.

La scheda mostra:

* nome/tipo
* punti vita o stato di integrità
* azioni disponibili
* eventuali tratti semplici

#### Automazione sui minion

Quando un minion subisce danni:

* i punti vita scalano automaticamente
* se, in base alle regole, la perdita di vita comporta:

  * perdita di azioni
  * riduzione di efficacia
  * eliminazione

lo schermo:

* applica automaticamente la conseguenza
* la rende **visivamente evidente** (es. azioni disattivate)

Il master può in ogni momento:

* modificare a mano punti vita
* riattivare o disattivare azioni
* ignorare l’automazione

---

### 3.3 Nemici rilevanti

I nemici rilevanti usano **la stessa struttura dei PG**.

L’unica differenza è visiva:

* colore
* icona
* etichetta “Nemico”

Dal punto di vista dell’interfaccia:

* hanno gli stessi pulsanti
* gli stessi campi modificabili
* gli stessi automatismi

---

## 4. Gestione del combattimento

Quando la scena è in combattimento:

* compare l’ordine dei turni
* il turno attivo è evidenziato
* il master ha un pulsante **“Avanza turno”**

Il sistema:

* non avanza turni automaticamente
* non blocca azioni
* non impedisce modifiche manuali

Il master può:

* saltare un turno
* tornare a narrazione
* intervenire su qualsiasi valore

---

## 5. Automazione della perdita di vita (nemici)

Quando un nemico (minion o rilevante) subisce danni:

1. Il master inserisce il danno (o lo applica tramite un pulsante)
2. I punti vita vengono scalati automaticamente
3. Se, in base alle regole, la nuova soglia di vita comporta:

   * perdita di azioni
   * limitazioni
   * stati

lo schermo:

* aggiorna automaticamente la scheda
* disattiva visivamente le azioni perse
* mostra chiaramente cosa è cambiato

Tutto resta **sempre modificabile a mano**.

---

## 6. Strumento di tiro dadi – Interfaccia

Accanto a ogni abilità c’è il pulsante **“Tira”**.

Premendolo:

1. Lo schermo calcola automaticamente **il numero di d10**
2. Esegue il tiro
3. Mostra:

   * risultati dei singoli dadi
   * successi
   * fallimenti (1)

### Calcoli automatici

* 8+ = successo
* 1 = fallimento che annulla un successo
* (se abilità livello 4) il 7 conta automaticamente come successo

---

## 7. 10 esplosivo – Interazione dettagliata

Se l’abilità è almeno di **livello 2**:

### Dopo un tiro

* lo schermo conta quanti **10** sono usciti
* compare un messaggio del tipo:

> “Sono usciti X risultati pari a 10.
> Quanti dadi aggiuntivi vuoi tirare?”

Il master/giocatore può scegliere:

* 0
* 1
* 2
* …
* fino a X

### Punto chiave dell’interfaccia

* la scelta viene fatta **una sola volta per ondata**
* non è possibile aggiungere dadi uno alla volta

---

### Dadi aggiuntivi

Quando vengono tirati i dadi extra:

* se escono altri 10
* lo schermo ripete **la stessa interazione**
* una nuova scelta unica
* il processo può continuare in loop

Lo schermo **non tira mai dadi extra automaticamente**.

---

## 8. Evidenza del rischio

Quando vengono aggiunti dadi extra:

* lo schermo segnala che:

  * aumenta il potenziale impatto del fallimento negativo
* senza calcolarlo al posto del master

Serve solo come **promemoria visivo**, non come vincolo.

---

## 9. Override manuale (principio fondamentale)

In qualsiasi momento il master può:

* cambiare un numero
* riattivare un’azione persa
* annullare una conseguenza automatica
* ignorare un risultato

L’interfaccia **non impedisce mai** una scelta del master.

---

## 10. Obiettivo dell’interfaccia

Lo scopo dello schermo è:

* ridurre errori di gestione
* rendere immediati gli effetti delle regole
* velocizzare i combattimenti
* senza mai togliere controllo al master

Il sistema:

* calcola
* applica
* segnala

Il master:

* decide
* corregge
* narra

---

## 11. Punti su cui chiediamo conferma

Questa interfaccia ti sembra:

* coerente con lo spirito del sistema?
* troppo automatica o nel giusto equilibrio?
* ci sono automatismi che preferiresti **solo segnalati** e non applicati?

Questo feedback serve per rifinire **il comportamento dello schermo**, non le regole.

---