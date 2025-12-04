<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'description'];

    /**
     * Ottiene un'impostazione dal database con cache
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("system_setting_{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            
            if (!$setting) {
                return $default;
            }

            return match($setting->type) {
                'boolean' => (bool) $setting->value,
                'integer' => (int) $setting->value,
                'json' => json_decode($setting->value, true),
                default => $setting->value,
            };
        });
    }

    /**
     * Imposta un valore
     */
    public static function set(string $key, $value, string $type = 'string', ?string $description = null): void
    {
        $storedValue = $type === 'json' ? json_encode($value) : (string) $value;

        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'type' => $type,
                'description' => $description,
            ]
        );

        Cache::forget("system_setting_{$key}");
    }

    /**
     * Ottiene la vista di default del vault
     */
    public static function getVaultDefaultView(): string
    {
        return static::get('vault_default_view', 'tree');
    }

    /**
     * Imposta la vista di default del vault
     */
    public static function setVaultDefaultView(string $view): void
    {
        if (!in_array($view, ['tree', 'graph'])) {
            throw new \InvalidArgumentException('Vista non valida. Usa "tree" o "graph".');
        }

        static::set('vault_default_view', $view, 'string', 'Vista predefinita del vault: tree o graph');
    }
}

