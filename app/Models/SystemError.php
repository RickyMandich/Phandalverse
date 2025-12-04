<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemError extends Model
{
    use HasFactory;

    protected $fillable = [
        'exception_class',
        'message',
        'file',
        'line',
        'trace',
        'request_url',
        'request_method',
        'user_agent',
        'user_id',
        'status',
        'admin_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    /**
     * L'utente che ha causato l'errore (se loggato)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * L'admin che ha risolto l'errore
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope per errori non risolti
     */
    public function scopeUnresolved($query)
    {
        return $query->whereIn('status', ['new', 'in_progress']);
    }

    /**
     * Scope per errori risolti
     */
    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    /**
     * Segna come in lavorazione
     */
    public function markAsInProgress(): void
    {
        $this->update(['status' => 'in_progress']);
    }

    /**
     * Segna come risolto
     */
    public function markAsResolved(User $admin, ?string $notes = null): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
            'admin_notes' => $notes ?? $this->admin_notes,
        ]);
    }

    /**
     * Segna come ignorato
     */
    public function markAsIgnored(User $admin, ?string $notes = null): void
    {
        $this->update([
            'status' => 'ignored',
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
            'admin_notes' => $notes ?? $this->admin_notes,
        ]);
    }

    /**
     * Ottieni il nome breve della classe eccezione
     */
    public function getShortClassNameAttribute(): string
    {
        return class_basename($this->exception_class);
    }

    /**
     * Ottieni un estratto del codice sorgente intorno alla linea dell'errore
     *
     * @param int $contextLines Numero di righe prima e dopo da mostrare
     * @return array|null ['lines' => array, 'start' => int, 'error_line' => int]
     */
    public function getCodeSnippet(int $contextLines = 5): ?array
    {
        if (!file_exists($this->file) || !is_readable($this->file)) {
            return null;
        }

        try {
            $fileLines = file($this->file);
            if ($fileLines === false) {
                return null;
            }

            $totalLines = count($fileLines);
            $errorLine = $this->line;

            // Calcola le righe di inizio e fine
            $startLine = max(1, $errorLine - $contextLines);
            $endLine = min($totalLines, $errorLine + $contextLines);

            // Estrai le righe (array è 0-indexed, le righe sono 1-indexed)
            $lines = [];
            for ($i = $startLine; $i <= $endLine; $i++) {
                $lines[$i] = rtrim($fileLines[$i - 1], "\r\n");
            }

            return [
                'lines' => $lines,
                'start' => $startLine,
                'end' => $endLine,
                'error_line' => $errorLine,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Ottieni il percorso relativo del file (senza il path base del progetto)
     */
    public function getRelativeFileAttribute(): string
    {
        $basePath = base_path() . DIRECTORY_SEPARATOR;
        if (str_starts_with($this->file, $basePath)) {
            return substr($this->file, strlen($basePath));
        }
        return $this->file;
    }
}

