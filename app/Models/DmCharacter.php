<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DmCharacter extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'stats',
    ];

    protected $casts = [
        'stats' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
