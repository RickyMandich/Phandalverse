<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'folder_name',
        'display_name',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    /**
     * Utenti che hanno accesso a questa campagna.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Gruppi di accesso appartenenti a questa campagna.
     */
    public function accessGroups(): HasMany
    {
        return $this->hasMany(AccessGroup::class);
    }

    /**
     * Iscritti Telegram per le notifiche di questa campagna.
     */
    public function telegramSubscribers(): HasMany
    {
        return $this->hasMany(TelegramSubscriber::class);
    }

    /**
     * Risolve il percorso fisico nel filesystem della cartella Vault della campagna.
     */
    public function vaultPath(?string $subpath = null): string
    {
        $base = base_path('Vault' . DIRECTORY_SEPARATOR . $this->folder_name);
        if ($subpath) {
            $cleaned = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $subpath), DIRECTORY_SEPARATOR);
            return $base . DIRECTORY_SEPARATOR . $cleaned;
        }
        return $base;
    }

    /**
     * Risolve il percorso del file map.json della campagna.
     */
    public function mapPath(): string
    {
        return $this->vaultPath('.normalize' . DIRECTORY_SEPARATOR . 'map.json');
    }

    /**
     * Risolve il percorso dei file di configurazione Obsidian della campagna.
     */
    public function obsidianPath(?string $file = null): string
    {
        $sub = '.obsidian' . ($file ? DIRECTORY_SEPARATOR . ltrim($file, '/\\') : '');
        return $this->vaultPath($sub);
    }

    /**
     * Risolve il percorso della cartella changelogs della campagna.
     */
    public function changelogsPath(?string $file = null): string
    {
        $sub = '.normalize' . DIRECTORY_SEPARATOR . 'changelogs' . ($file ? DIRECTORY_SEPARATOR . ltrim($file, '/\\') : '');
        return $this->vaultPath($sub);
    }
}
