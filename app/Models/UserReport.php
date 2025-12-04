<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserReport extends Model
{
    protected $fillable = [
        'category',
        'description',
        'page_urls',
        'user_id',
        'status',
        'admin_notes',
    ];

    protected $casts = [
        'page_urls' => 'array',
    ];

    /**
     * Categorie disponibili
     */
    public const CATEGORIES = [
        'logic' => 'Problema logico',
        'display' => 'Problema di visualizzazione',
        'other' => 'Altro',
    ];

    /**
     * Stati disponibili
     */
    public const STATUSES = [
        'pending' => 'In attesa',
        'in_progress' => 'In lavorazione',
        'resolved' => 'Risolto',
        'rejected' => 'Rifiutato',
    ];

    /**
     * Relazione con l'utente
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ottiene il nome leggibile della categoria
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    /**
     * Ottiene il nome leggibile dello stato
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Scope per filtrare per stato
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope per segnalazioni in attesa
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}

