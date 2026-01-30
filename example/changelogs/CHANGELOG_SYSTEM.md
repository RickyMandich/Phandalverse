# Sistema di Changelog Automatico

## Panoramica

Il sistema di changelog traccia automaticamente tutte le modifiche ai file `.md` del vault per ogni versione del progetto. I dati vengono generati in formato JSON e sono pronti per essere integrati nella web app Laravel.

## Workflow

1. **Modifica dei file**: L'utente modifica i file `.md` nel vault usando Obsidian
2. **Deploy**: Esegue `bash bash/all.sh` (con opzioni `-v`, `-p`, o `-m` se necessario)
3. **Normalizzazione**: Lo script aggiorna `map.json` con i nuovi file
4. **Versioning**: Incrementa automaticamente la versione
5. **Commit**: Esegue commit su git con la nuova versione
6. **Generazione Changelog**: **NUOVO** - Genera automaticamente il changelog JSON per questa versione
7. **Deploy FTP**: Carica i file modificati (inclusi i changelog) su Altervista

## Struttura dei File

### Directory dei Changelog

```
.normalize/changelogs/
├── index.json              # Indice di tutte le versioni
├── v_0_0_1.json           # Changelog versione v 0.0.1
├── v_0_0_2.json           # Changelog versione v 0.0.2
├── v_1_0_0.json           # Changelog versione v 1.0.0
└── ...
```

**Nota**: I nomi dei file sostituiscono spazi e punti con underscore (es. "v 1.2.3" → `v_1_2_3.json`)

## Formato dei Dati

### 1. File Indice (`index.json`)

Contiene l'elenco di tutte le versioni disponibili, ordinate dalla più recente alla più vecchia.

**Struttura**:
```json
{
	"versions": [
		{
			"version": "v 1.2.3",
			"date": "2026-01-29 15:30:45",
			"commit_hash": "abc123def456789",
			"changes_count": 5
		},
		{
			"version": "v 1.2.2",
			"date": "2026-01-28 14:20:30",
			"commit_hash": "def789ghi012345",
			"changes_count": 3
		}
	]
}
```

**Campi**:
- `version` (string): Versione nel formato "v X.Y.Z"
- `date` (string): Data e ora del commit in formato "YYYY-MM-DD HH:MM:SS"
- `commit_hash` (string): Hash completo del commit git
- `changes_count` (integer): Numero di file `.md` modificati in questa versione

### 2. File Changelog Versione (`v_X_Y_Z.json`)

Contiene i dettagli completi delle modifiche per una specifica versione.

**Struttura**:
```json
{
	"version": "v 1.2.3",
	"date": "2026-01-29 15:30:45",
	"commit_hash": "abc123def456789",
	"commit_message": "aggiornamento 2026 01 29 15:30 [v 1.2.3] - Aggiunta nuova location",
	"changes": [
		{
			"file": "locations/phandalin.md",
			"display_name": "Phandalin",
			"status": "Modified",
			"diff": "diff --git a/locations/phandalin.md b/locations/phandalin.md\n..."
		},
		{
			"file": "artefatti/party/nuovo-artefatto.md",
			"display_name": "Nuovo Artefatto",
			"status": "Added",
			"diff": "diff --git a/artefatti/party/nuovo-artefatto.md b/artefatti/party/nuovo-artefatto.md\n..."
		},
		{
			"file": "locations/vecchia-location.md",
			"display_name": "Vecchia Location",
			"status": "Deleted",
			"diff": "diff --git a/locations/vecchia-location.md b/locations/vecchia-location.md\n..."
		}
	]
}
```

**Campi Root**:
- `version` (string): Versione nel formato "v X.Y.Z"
- `date` (string): Data e ora del commit in formato "YYYY-MM-DD HH:MM:SS"
- `commit_hash` (string): Hash completo del commit git
- `commit_message` (string): Messaggio completo del commit
- `changes` (array): Array di oggetti che rappresentano i file modificati

**Campi di ogni oggetto in `changes`**:
- `file` (string): Percorso relativo del file dalla root del vault
- `display_name` (string): Nome visualizzato del file (estratto da `map.json`)
- `status` (string): Tipo di modifica. Valori possibili:
  - `"Added"`: File aggiunto
  - `"Modified"`: File modificato
  - `"Deleted"`: File eliminato
  - `"Renamed"`: File rinominato
  - `"Copied"`: File copiato
  - `"Changed"`: Altro tipo di modifica
- `diff` (string): Output completo del comando `git show` per questo file, contenente le differenze line-by-line

### Formato del Diff

Il campo `diff` contiene l'output standard di git in formato unified diff:

```
diff --git a/file.md b/file.md
index abc123..def456 100644
--- a/file.md
+++ b/file.md
@@ -10,7 +10,7 @@ Contenuto...
 Linea invariata
 Linea invariata
-Linea rimossa
+Linea aggiunta
 Linea invariata
```

**Legenda**:
- Righe che iniziano con `-`: Contenuto rimosso
- Righe che iniziano con `+`: Contenuto aggiunto
- Righe senza prefisso: Contenuto invariato (contesto)
- `@@ -10,7 +10,7 @@`: Indica la posizione delle modifiche (riga 10, 7 righe nel vecchio file → riga 10, 7 righe nel nuovo file)

## Integrazione nella Web App

### Endpoint Suggeriti

#### 1. Lista Versioni
**GET** `/api/changelog` o `/changelog`

Restituisce l'elenco di tutte le versioni disponibili.

**Implementazione Laravel**:
```php
public function index()
{
    $indexPath = storage_path('app/vault/.normalize/changelogs/index.json');
    $index = json_decode(file_get_contents($indexPath), true);
    
    return response()->json($index);
}
```

#### 2. Dettagli Versione
**GET** `/api/changelog/{version}` o `/changelog/{version}`

Restituisce i dettagli completi di una versione specifica.

**Parametri**:
- `version`: Versione nel formato "v_X_Y_Z" (con underscore)

**Implementazione Laravel**:
```php
public function show($version)
{
    // Sanitizza l'input
    $version = preg_replace('/[^a-zA-Z0-9_]/', '', $version);
    
    $changelogPath = storage_path("app/vault/.normalize/changelogs/{$version}.json");
    
    if (!file_exists($changelogPath)) {
        abort(404, 'Versione non trovata');
    }
    
    $changelog = json_decode(file_get_contents($changelogPath), true);
    
    return response()->json($changelog);
}
```

### Esempio di Visualizzazione

#### Pagina Lista Versioni

```html
<div class="changelog-list">
    @foreach($versions as $version)
    <div class="version-card">
        <h3>{{ $version['version'] }}</h3>
        <p class="date">{{ $version['date'] }}</p>
        <p class="changes-count">{{ $version['changes_count'] }} file modificati</p>
        <a href="/changelog/{{ str_replace([' ', '.'], '_', $version['version']) }}">
            Vedi dettagli
        </a>
    </div>
    @endforeach
</div>
```

#### Pagina Dettagli Versione

```html
<div class="changelog-detail">
    <h2>{{ $changelog['version'] }}</h2>
    <p class="date">{{ $changelog['date'] }}</p>
    <p class="commit">Commit: {{ substr($changelog['commit_hash'], 0, 7) }}</p>
    <p class="message">{{ $changelog['commit_message'] }}</p>
    
    <h3>Modifiche ({{ count($changelog['changes']) }})</h3>
    
    @foreach($changelog['changes'] as $change)
    <div class="change-item status-{{ strtolower($change['status']) }}">
        <h4>
            <span class="badge">{{ $change['status'] }}</span>
            {{ $change['display_name'] }}
        </h4>
        <p class="file-path">{{ $change['file'] }}</p>
        
        @if($change['status'] !== 'Deleted')
        <a href="/vault/{{ $change['file'] }}">Visualizza file</a>
        @endif
        
        <!-- Opzionale: Mostra il diff -->
        <details>
            <summary>Mostra modifiche</summary>
            <pre class="diff">{{ $change['diff'] }}</pre>
        </details>
    </div>
    @endforeach
</div>
```

## Note Tecniche

### Generazione Automatica

Il changelog viene generato automaticamente da `bash/generate-changelog.sh` che:
1. Legge la versione dall'ultimo commit
2. Usa `git diff-tree` per trovare i file modificati
3. Consulta `.normalize/map.json` per i nomi visualizzati
4. Usa `git show` per ottenere i diff completi
5. Genera il file JSON della versione
6. Aggiorna l'indice

### File Tracciati

- ✅ Solo file `.md` vengono tracciati
- ❌ File in `.trash/` vengono ignorati
- ❌ File in `bash/` vengono ignorati

### Sincronizzazione

I file di changelog vengono:
1. Tracciati da git (vedi `.gitignore`)
2. Caricati automaticamente su Altervista via FTP insieme agli altri file modificati
3. Disponibili nella stessa directory del vault sul server

## Esempi Completi

Per esempi dettagliati di output JSON con tutti i tipi di modifiche (Added, Modified, Deleted) e esempi di codice Laravel/Blade, consulta:

📄 **`bash/CHANGELOG_EXAMPLES.md`**

Questo documento contiene:
- Esempi reali di `index.json`
- Esempi di file versione con diverse tipologie di modifiche
- Spiegazione dettagliata del formato diff
- Esempi di codice PHP/Laravel per l'integrazione
- Esempi di template Blade per la visualizzazione

## Troubleshooting

### Il changelog non viene generato

Verifica che:
- Il commit contenga modifiche a file `.md`
- Il messaggio di commit contenga una versione nel formato `[v X.Y.Z]`
- Lo script `generate-changelog.sh` abbia i permessi di esecuzione

### I nomi visualizzati sono errati

Verifica che:
- Il file `.normalize/map.json` sia aggiornato
- Lo script `normalize.sh` sia stato eseguito prima del commit

### Il diff è vuoto

Questo può accadere se:
- Il file è stato aggiunto ma è vuoto
- Il file è stato eliminato (il diff mostrerà solo righe con `-`)

