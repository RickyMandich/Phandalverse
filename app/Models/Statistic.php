<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Statistic extends Model
{
    /**
     * Disable updated_at timestamp (we only need created_at)
     */
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'ip_address',
        'url',
        'http_method',
        'user_agent',
        'referrer',
        'response_status',
        'response_time',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'response_time' => 'float',
        ];
    }

    /**
     * Get the user that made the request
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by specific user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by IP address
     */
    public function scopeByIp($query, $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Get grouped statistics by user and IP
     */
    public static function getGroupedByUserAndIp($startDate = null, $endDate = null)
    {
        $query = self::query()
            ->selectRaw('
                user_id,
                ip_address,
                COUNT(*) as request_count,
                MAX(created_at) as last_activity,
                MAX(url) as sample_url
            ')
            ->groupBy('user_id', 'ip_address')
            ->orderByDesc('request_count');

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        return $query;
    }
}
