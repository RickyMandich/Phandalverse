<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramSubscriber extends Model
{
    protected $fillable = ['campaign_id', 'chat_id', 'thread_id', 'username', 'telegram_user_id'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
