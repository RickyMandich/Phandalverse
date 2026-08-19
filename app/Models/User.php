<?php

namespace App\Models;

use App\Mail\VerifyEmailMailable;
use App\Services\EmailQueueService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Override per inviare la mail di verifica tramite la nostra coda custom
     */
    public function sendEmailVerificationNotification()
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(120), // 2 ore di validità
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())]
        );

        EmailQueueService::queue(
            new VerifyEmailMailable($this, $verificationUrl),
            $this->email,
            'user_verification'
        );
    }

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
        'master_request',
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

    protected ?array $accessGroupSlugChainCache = null;

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

    public function accessGroups(): BelongsToMany
    {
        return $this->belongsToMany(AccessGroup::class);
    }


    /**
     * Insieme di tutti gli slug di gruppo "visibili" per l'utente:
     * i propri gruppi diretti + tutti i loro antenati.
     * Cachato per-request per evitare N query durante il parsing di una nota.
     */
    public function visibleAccessGroupSlugs(): array
    {
        if ($this->accessGroupSlugChainCache !== null) {
            return $this->accessGroupSlugChainCache;
        }

        $slugs = [];
        foreach ($this->accessGroups as $group) {
            $slugs = array_merge($slugs, $group->ancestorSlugs());
        }

        return $this->accessGroupSlugChainCache = array_unique(array_map('strtolower', $slugs));
    }

    /**
     * Verifica se l'utente ha accesso ad almeno uno dei gruppi richiesti (logica OR).
     * Master bypassa sempre, senza coinvolgere la gerarchia.
     */
    public function hasAccessToAnyGroup(array $requiredSlugs): bool
    {
        if ($this->master) {
            return true;
        }

        $requiredLower = array_map('strtolower', $requiredSlugs);
        return count(array_intersect($requiredLower, $this->visibleAccessGroupSlugs())) > 0;
    }
}
