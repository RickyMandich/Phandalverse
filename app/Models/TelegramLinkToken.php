<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TelegramLinkToken extends Model
{
    protected $fillable = [
        'token',
        'type',
        'telegram_user_id',
        'telegram_username',
        'chat_id',
        'thread_id',
        'active',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Verifica se il token è ancora valido (attivo e non scaduto).
     */
    public function isValid(): bool
    {
        return $this->active && $this->expires_at->isFuture();
    }

    /**
     * Genera un nuovo token di collegamento, valido per 1 ora.
     *
     * @param string $type 'personal' oppure 'group'
     */
    public static function generateFor(string $type, int $telegramUserId, ?string $telegramUsername, string $chatId, ?string $threadId = null): self
    {
        return static::create([
            'token' => Str::random(48),
            'type' => $type,
            'telegram_user_id' => $telegramUserId,
            'telegram_username' => $telegramUsername,
            'chat_id' => $chatId,
            'thread_id' => $threadId,
            'active' => true,
            'expires_at' => now()->addHour(),
        ]);
    }
}
