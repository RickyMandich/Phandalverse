# Esempi di Output JSON del Sistema di Changelog

Questo documento mostra esempi concreti dei file JSON generati dal sistema di changelog.

## Esempio 1: File Indice (`index.json`)

```json
{
	"versions": [
		{
			"version": "v 1.2.3",
			"date": "2026-01-29 15:30:45",
			"commit_hash": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0",
			"changes_count": 3
		},
		{
			"version": "v 1.2.2",
			"date": "2026-01-28 14:20:30",
			"commit_hash": "b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1",
			"changes_count": 1
		},
		{
			"version": "v 1.2.1",
			"date": "2026-01-27 10:15:22",
			"commit_hash": "c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2",
			"changes_count": 5
		},
		{
			"version": "v 1.2.0",
			"date": "2026-01-26 09:00:00",
			"commit_hash": "d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3",
			"changes_count": 12
		}
	]
}
```

**Note**:
- Le versioni sono ordinate dalla più recente alla più vecchia
- `changes_count` indica quanti file `.md` sono stati modificati
- `commit_hash` è l'hash completo del commit git (40 caratteri)

## Esempio 2: File Versione con File Modificato (`v_1_2_3.json`)

```json
{
	"version": "v 1.2.3",
	"date": "2026-01-29 15:30:45",
	"commit_hash": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0",
	"commit_message": "aggiornamento 2026 01 29 15:30 [v 1.2.3] - Aggiornata descrizione di Phandalin",
	"changes": [
		{
			"file": "locations/phandalin.md",
			"display_name": "Phandalin",
			"status": "Modified",
			"diff": "diff --git a/locations/phandalin.md b/locations/phandalin.md\nindex 1234567..abcdefg 100644\n--- a/locations/phandalin.md\n+++ b/locations/phandalin.md\n@@ -1,10 +1,10 @@\n # Phandalin\n \n-Phandalin è un piccolo villaggio di frontiera.\n+Phandalin è un prospero villaggio di frontiera, recentemente ricostruito.\n \n ## Storia\n \n-Il villaggio fu distrutto anni fa.\n+Il villaggio fu distrutto anni fa da una banda di orchi, ma è stato ricostruito dai coloni.\n \n ## Luoghi Importanti\n \n@@ -15,3 +15,7 @@\n - Locanda del Maiale Addormentato\n - Emporio di Barthen\n - Municipio\n+\n+## Eventi Recenti\n+\n+Gli avventurieri hanno liberato il villaggio dalla minaccia dei Redbrands."
		}
	]
}
```

**Note sul diff**:
- Le righe che iniziano con `-` sono state rimosse
- Le righe che iniziano con `+` sono state aggiunte
- `@@ -1,10 +1,10 @@` indica la posizione delle modifiche (riga 1, 10 righe → riga 1, 10 righe)
- Le righe senza prefisso sono il contesto (righe non modificate)

## Esempio 3: File Versione con File Aggiunto (`v_1_2_2.json`)

```json
{
	"version": "v 1.2.2",
	"date": "2026-01-28 14:20:30",
	"commit_hash": "b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1",
	"commit_message": "aggiornamento 2026 01 28 14:20 [v 1.2.2] - Aggiunto nuovo artefatto",
	"changes": [
		{
			"file": "artefatti/party/spada-fiammeggiante.md",
			"display_name": "Spada Fiammeggiante",
			"status": "Added",
			"diff": "diff --git a/artefatti/party/spada-fiammeggiante.md b/artefatti/party/spada-fiammeggiante.md\nnew file mode 100644\nindex 0000000..1234567\n--- /dev/null\n+++ b/artefatti/party/spada-fiammeggiante.md\n@@ -0,0 +1,15 @@\n+# Spada Fiammeggiante\n+\n+## Descrizione\n+\n+Una spada lunga avvolta da fiamme magiche.\n+\n+## Proprietà\n+\n+- **Danno**: 1d8 + 1d6 fuoco\n+- **Bonus**: +2 ai tiri per colpire e ai danni\n+- **Proprietà Speciale**: Emette luce intensa in un raggio di 12 metri\n+\n+## Storia\n+\n+Forgiata nelle fucine di Gauntlgrym dai nani del clan Battlehammer."
		}
	]
}
```

**Note sul diff per file aggiunto**:
- `new file mode 100644` indica che è un nuovo file
- `--- /dev/null` indica che non esisteva prima
- `+++ b/artefatti/party/spada-fiammeggiante.md` indica il nuovo file
- `@@ -0,0 +1,15 @@` indica che sono state aggiunte 15 righe partendo dalla riga 1
- Tutte le righe iniziano con `+` perché tutto il contenuto è nuovo

## Esempio 4: File Versione con File Eliminato (`v_1_2_1.json`)

```json
{
	"version": "v 1.2.1",
	"date": "2026-01-27 10:15:22",
	"commit_hash": "c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2",
	"commit_message": "aggiornamento 2026 01 27 10:15 [v 1.2.1] - Rimosso PNG morto",
	"changes": [
		{
			"file": "png/nemici/goblin-capo.md",
			"display_name": "Goblin Capo",
			"status": "Deleted",
			"diff": "diff --git a/png/nemici/goblin-capo.md b/png/nemici/goblin-capo.md\ndeleted file mode 100644\nindex 1234567..0000000\n--- a/png/nemici/goblin-capo.md\n+++ /dev/null\n@@ -1,12 +0,0 @@\n-# Goblin Capo\n-\n-## Descrizione\n-\n-Il capo della tribù goblin.\n-\n-## Statistiche\n-\n-- **CA**: 15\n-- **PF**: 25\n-- **Attacco**: +4, 1d8+2\n-"
		}
	]
}
```

**Note sul diff per file eliminato**:
- `deleted file mode 100644` indica che il file è stato eliminato
- `--- a/png/nemici/goblin-capo.md` indica il file originale
- `+++ /dev/null` indica che non esiste più
- `@@ -1,12 +0,0 @@` indica che 12 righe sono state rimosse
- Tutte le righe iniziano con `-` perché tutto il contenuto è stato rimosso

## Esempio 5: File Versione con Modifiche Multiple (`v_1_2_0.json`)

```json
{
	"version": "v 1.2.0",
	"date": "2026-01-26 09:00:00",
	"commit_hash": "d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3",
	"commit_message": "aggiornamento 2026 01 26 09:00 [v 1.2.0] - Aggiornamento campagna",
	"changes": [
		{
			"file": "artefatti/party/artefatto-necromante.md",
			"display_name": "Occhio di Atos",
			"status": "Modified",
			"diff": "diff --git a/artefatti/party/artefatto-necromante.md b/artefatti/party/artefatto-necromante.md\nindex abc1234..def5678 100644\n--- a/artefatti/party/artefatto-necromante.md\n+++ b/artefatti/party/artefatto-necromante.md\n@@ -10,3 +10,7 @@\n ## Poteri\n \n - Animare morti\n+- Visione della morte\n+- Comunicare con i defunti\n+\n+**Nota**: L'uso prolungato corrompe l'anima del possessore."
		},
		{
			"file": "locations/neverwinter.md",
			"display_name": "Neverwinter",
			"status": "Added",
			"diff": "diff --git a/locations/neverwinter.md b/locations/neverwinter.md\nnew file mode 100644\nindex 0000000..abc1234\n--- /dev/null\n+++ b/locations/neverwinter.md\n@@ -0,0 +1,8 @@\n+# Neverwinter\n+\n+La Città dei Mestieri Qualificati.\n+\n+## Descrizione\n+\n+Neverwinter è una grande città portuale sulla Costa della Spada."
		},
		{
			"file": "png/alleati/sildar-hallwinter.md",
			"display_name": "Sildar Hallwinter",
			"status": "Modified",
			"diff": "diff --git a/png/alleati/sildar-hallwinter.md b/png/alleati/sildar-hallwinter.md\nindex 1111111..2222222 100644\n--- a/png/alleati/sildar-hallwinter.md\n+++ b/png/alleati/sildar-hallwinter.md\n@@ -5,7 +5,7 @@\n Membro dell'Alleanza dei Lord.\n \n-## Status\n+## Status Attuale\n \n-Disperso\n+Salvato dagli avventurieri, ora si trova a Phandalin."
		}
	]
}
```

**Note**:
- Una singola versione può contenere modifiche a più file
- Ogni file ha il suo diff separato
- I tipi di modifica possono essere misti (Added, Modified, Deleted)

## Utilizzo dei Dati nella Web App

### Esempio 1: Mostrare la Lista delle Versioni

```php
// Controller
$index = json_decode(file_get_contents('.normalize/changelogs/index.json'), true);
return view('changelog.index', ['versions' => $index['versions']]);
```

```blade
<!-- View -->
@foreach($versions as $version)
<div class="version-card">
    <h3>{{ $version['version'] }}</h3>
    <p>{{ $version['date'] }}</p>
    <span class="badge">{{ $version['changes_count'] }} modifiche</span>
    <a href="/changelog/{{ str_replace([' ', '.'], '_', $version['version']) }}">
        Dettagli
    </a>
</div>
@endforeach
```

### Esempio 2: Mostrare i Dettagli di una Versione

```php
// Controller
$version = str_replace([' ', '.'], '_', $versionParam);
$changelog = json_decode(file_get_contents(".normalize/changelogs/{$version}.json"), true);
return view('changelog.show', ['changelog' => $changelog]);
```

```blade
<!-- View -->
<h2>{{ $changelog['version'] }}</h2>
<p>{{ $changelog['date'] }}</p>

<h3>Modifiche</h3>
@foreach($changelog['changes'] as $change)
<div class="change-item status-{{ strtolower($change['status']) }}">
    <span class="badge badge-{{ strtolower($change['status']) }}">
        {{ $change['status'] }}
    </span>
    <strong>{{ $change['display_name'] }}</strong>
    <small>{{ $change['file'] }}</small>
    
    @if($change['status'] !== 'Deleted')
    <a href="/vault/{{ $change['file'] }}">Visualizza</a>
    @endif
</div>
@endforeach
```

### Esempio 3: Mostrare il Diff (Opzionale)

```blade
<details>
    <summary>Mostra modifiche</summary>
    <pre class="diff">{{ $change['diff'] }}</pre>
</details>
```

Con CSS per colorare il diff:

```css
.diff {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 1rem;
    overflow-x: auto;
}

.diff .line-added {
    background: #1a4d1a;
    color: #4ade80;
}

.diff .line-removed {
    background: #4d1a1a;
    color: #f87171;
}
```

## Conclusione

Questi esempi mostrano esattamente come appariranno i dati generati dal sistema di changelog. I backend developer possono usare questi esempi come riferimento per implementare la visualizzazione nella web app Laravel.

