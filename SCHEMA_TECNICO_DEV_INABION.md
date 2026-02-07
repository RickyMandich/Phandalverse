# Powerfail System 5.3

## Schermo del Master Digitale

### Specifica tecnica autosufficiente per lo sviluppo

---

## 1. Scopo della pagina

Questa pagina serve a **supportare il Master** durante una sessione di Powerfail System 5.3, riducendo il carico di gestione manuale senza mai sostituirsi alle sue decisioni.

Principi fondamentali:

* tutte le regole restano in mano al Master
* alcune conseguenze vengono **calcolate e applicate automaticamente**
* **ogni valore può sempre essere modificato manualmente**
* la pagina deve funzionare interamente da sola, senza dipendenze esterne

---

## 2. Struttura generale della pagina

La pagina è **un’unica vista**, persistente per tutta la sessione.

È divisa logicamente in tre aree:

1. **Contesto di scena**
2. **Gestione attori in scena**
3. **Strumenti (combattimento e dadi)**

Non esistono cambi di pagina durante il gioco.

---

## 3. Contesto di scena

### Componenti

* Nome scena (campo testo modificabile)
* Note del Master (campo testo libero, privato)
* Stato scena:

  * Narrativa
  * Combattimento

### Comportamento

* Cambiare lo stato:

  * mostra o nasconde strumenti di combattimento
  * **non** applica regole
  * **non** forza transizioni

---

## 4. Attori in scena – modello dati comune

Tutti gli elementi che partecipano al sistema condividono una struttura base.

### Tipi di attori

* **Personaggio Giocante**
* **Minion**
* **Nemico Rilevante**

### Struttura base (concettuale)

Ogni attore possiede sempre:

* Nome
* Tipo
* Punti Vita attuali
* Punti Vita massimi
* Azioni disponibili
* Stati attivi

Tutti questi valori:

* sono visibili al Master
* sono **sempre modificabili manualmente**

---

## 5. Personaggi Giocanti

### Contenuto della scheda

* Caratteristiche
* Abilità (con livello)
* Punti Vita
* Fatica
* Stati
* Azioni

### Interazione

* Ogni abilità ha un pulsante **“Tira”**
* Ogni valore numerico è cliccabile e modificabile
* Nessuna automazione forzata su azioni o fatica

---

## 6. Minion

I minion rappresentano nemici semplici e numerosi.

### Contenuto della scheda

* Nome o tipo
* Punti Vita
* Azioni
* Eventuali tratti semplici

La scheda è **compatta**, senza dettagli non necessari.

---

### 6.1 Automazione sui Minion

Quando i punti vita di un minion cambiano:

1. I punti vita vengono aggiornati
2. Il sistema verifica se, in base alle soglie previste:

   * devono essere perse azioni
   * devono essere applicate limitazioni
   * il minion è eliminato
3. Le conseguenze vengono applicate automaticamente

Le azioni perse:

* vengono disattivate visivamente
* non vengono eliminate definitivamente

Il Master può sempre:

* modificare i punti vita
* riattivare azioni
* annullare qualsiasi conseguenza

---

## 7. Nemici Rilevanti

I nemici rilevanti sono **meccanicamente equivalenti ai personaggi giocanti**.

### Contenuto della scheda

* Caratteristiche
* Abilità (con livello)
* Punti Vita
* Fatica
* Stati
* Azioni

### Differenze rispetto ai PG

* marcatura visiva come “Nemico”
* nessuna differenza nelle regole

---

### 7.1 Automazione sui Nemici Rilevanti

Quando i punti vita cambiano:

1. Il sistema scala i punti vita
2. Controlla le soglie di danno
3. Applica automaticamente:

   * perdita di azioni
   * stati
   * limitazioni

Ogni effetto applicato:

* è chiaramente visibile
* è sempre reversibile manualmente

---

## 8. Gestione del combattimento

### Elementi visivi

* Ordine dei turni
* Evidenziazione del turno attivo
* Pulsante **“Avanza turno”**

### Comportamento

* Il turno **avanza solo su input del Master**
* Nessuna azione viene bloccata automaticamente
* Il Master può:

  * saltare turni
  * tornare alla narrativa
  * modificare qualsiasi valore

---

## 9. Sistema di tiro dadi

### Regole base implementate

* Si tirano **solo d10**
* Numero di dadi = valore di caratteristica + valore di abilità
* Un dado con risultato:

  * **8 o più** → successo
  * **1** → fallimento (annulla un successo)
* Se abilità livello 4:

  * **7 conta come successo**

---

## 10. Interfaccia di tiro

### Flusso base

1. Click su **“Tira”**
2. Calcolo automatico del numero di d10
3. Lancio dei dadi
4. Visualizzazione dei risultati
5. Calcolo successi e fallimenti

Tutti i risultati sono sempre visibili.

---

## 11. 10 esplosivo – Specifica completa

### Attivazione

* Solo se l’abilità è **livello 2 o superiore**

### Regola

* Ogni risultato pari a **10** consente di tirare **un dado aggiuntivo**
* I dadi aggiuntivi **non vengono tirati automaticamente**

---

### 11.1 Meccanica di scelta (fondamentale)

Dopo ogni tiro:

1. Il sistema conta quanti **10** sono usciti
2. Il sistema chiede **una sola volta**:

   > “Quanti dadi aggiuntivi vuoi tirare?”

Opzioni:

* da 0 fino al numero di 10 ottenuti

Vincoli:

* non è possibile aggiungere dadi uno alla volta
* la scelta non è modificabile dopo il tiro dei dadi extra

---

### 11.2 Loop esplosivo

Quando vengono tirati dadi aggiuntivi:

* se escono nuovi 10
* il sistema ripete **lo stesso identico processo**
* una nuova scelta unica
* il ciclo può continuare più volte

---

### 11.3 Rischio

Ogni ondata di dadi extra:

* aumenta il potenziale impatto del fallimento negativo

Il sistema:

* **segnala visivamente il rischio**
* **non calcola automaticamente il fallimento negativo**

---

## 12. Override manuale (regola globale)

In qualsiasi momento il Master può:

* cambiare punti vita
* modificare azioni
* aggiungere o rimuovere stati
* ignorare un risultato
* correggere un’automazione

Nessun campo è bloccato.
Nessuna conseguenza è irreversibile.

---

## 13. Filosofia di implementazione

Il sistema:

* calcola
* applica
* segnala

Il Master:

* decide
* corregge
* narra

Questa separazione **non deve mai rompersi**.

---

## 14. Stato della specifica

* ✔ Documento autosufficiente
* ✔ Nessun riferimento esterno necessario
* ✔ Tutti i flussi definiti
* ✔ Pronto per implementazione diretta

---