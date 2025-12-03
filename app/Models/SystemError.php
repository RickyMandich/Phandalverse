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
}

