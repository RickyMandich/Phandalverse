<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'admin',
        'master',
        'master_utils',
        'showEmbedLink',
        'collapseEmbed',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'master_utils' => 'boolean',
            'master' => 'boolean',
            // 'password' => 'hashed', riga rimossa per gestire l'hashing in maniera personalizzata
        ];
    }

    /**
     * Mutatore personalizzato per la password
     * Non hasha se la password inizia con V2: (già processata)
     */
    public function setPasswordAttribute($value)
    {
        // Se inizia con V2:, è già stata hashata con il nostro sistema custom
        if (str_starts_with($value, 'V2:')) {
            $this->attributes['password'] = $value;
        }
        // Se inizia con $2y$, è già un hash bcrypt standard
        elseif (str_starts_with($value, '$2y$') || str_starts_with($value, '$2a$')) {
            $this->attributes['password'] = $value;
        }
        // Altrimenti, è una password in chiaro da hashare
        else {
            $this->attributes['password'] = Hash::make($value);
        }
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->admin == 1;
    }

    /**
     * Check if user can use master utils
     */
    public function isMasterUtils(): bool
    {
        return $this->master || $this->master_utils;
    }

    /**
     * Check if user is full master
     */
    public function isMaster(): bool
    {
        return $this->master;
    }

    /**
     * Get all admin users for notifications
     */
    public static function getAdmins()
    {
        return static::where('admin', 1)->get();
    }
}
