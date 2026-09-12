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
        'default_campaign_id',
        'telegram_user_id',
        'telegram_username',
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

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class);
    }

    public function defaultCampaign()
    {
        return $this->belongsTo(Campaign::class, 'default_campaign_id');
    }

    /**
     * Ritorna tutte le campagne a cui l'utente ha accesso, ordinate per `order` asc.
     * Per il Master ritorna tutte le campagne registrate a sistema.
     */
    public function accessibleCampaigns()
    {
        if ($this->isMaster()) {
            return Campaign::orderBy('order')->get();
        }

        return $this->campaigns()->orderBy('order')->get();
    }

    /**
     * Verifica se l'utente ha accesso a una specifica campagna.
     * Il Master ha sempre accesso a tutte le campagne.
     */
    public function hasAccessToCampaign(Campaign|string|int $campaign): bool
    {
        if ($this->isMaster()) {
            return true;
        }

        if ($campaign instanceof Campaign) {
            $campaignId = $campaign->id;
        } elseif (is_numeric($campaign)) {
            $campaignId = (int) $campaign;
        } else {
            $found = Campaign::where('folder_name', $campaign)->first();
            if (!$found) {
                return false;
            }
            $campaignId = $found->id;
        }

        return $this->campaigns()->where('campaigns.id', $campaignId)->exists();
    }

    /**
     * Verifica se l'utente ha collegato il proprio account Telegram (chat privata).
     */
    public function isTelegramLinked(): bool
    {
        return !empty($this->telegram_user_id);
    }

    /**
     * Trova l'utente del sito collegato a un determinato ID utente Telegram.
     */
    public static function findByTelegramUserId(int $telegramUserId): ?self
    {
        return static::where('telegram_user_id', $telegramUserId)->first();
    }

    /**
     * Risolve la campagna iniziale da mostrare all'utente:
     * 1. Se impostata la default_campaign_id ed è accessibile -> usa quella.
     * 2. Altrimenti -> campagna con order più basso tra quelle accessibili.
     * 3. Fallback -> prima campagna esistente nel sistema.
     */
    public function resolveInitialCampaign(): ?Campaign
    {
        if ($this->default_campaign_id) {
            $default = Campaign::find($this->default_campaign_id);
            if ($default && $this->hasAccessToCampaign($default)) {
                return $default;
            }
        }

        $firstAccessible = $this->accessibleCampaigns()->first();
        if ($firstAccessible) {
            return $firstAccessible;
        }

        return Campaign::orderBy('order')->first();
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
