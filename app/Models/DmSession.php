<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DmSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'share_code',
        'data',
        'system',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (!$session->share_code) {
                $session->share_code = self::generateUniqueCode();
            }
        });
    }

    public static function generateUniqueCode()
    {
        do {
            $code = strtoupper(\Illuminate\Support\Str::random(3)) . '-' . strtoupper(\Illuminate\Support\Str::random(3));
        } while (self::where('share_code', $code)->exists());

        return $code;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
