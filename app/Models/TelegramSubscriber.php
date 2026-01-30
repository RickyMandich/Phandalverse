<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramSubscriber extends Model
{
    protected $fillable = ['chat_id', 'username'];
}
