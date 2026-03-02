<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAccessLog extends Model
{
    protected $table = 'user_access_logs';

    protected $fillable = [
        'user_id',
        'session_id',
        'action',
        'ip_address',
        'user_agent',
        'browser',
        'platform',
        'device',
        'country',
        'city',
        'region',
        'latitude',
        'longitude',
        'timezone',
        'isp',
        'mac_address',
        'login_at',
        'logout_at',
        'session_duration',
        'additional_data'
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'additional_data' => 'array'
    ];

    /**
     * Get the user that owns the access log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by action type.
     */
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get recent logs.
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get formatted session duration.
     */
    public function getFormattedSessionDurationAttribute()
    {
        if (!$this->session_duration) {
            return null;
        }

        $hours = floor($this->session_duration / 3600);
        $minutes = floor(($this->session_duration % 3600) / 60);
        $seconds = $this->session_duration % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    /**
     * Get location string.
     */
    public function getLocationAttribute()
    {
        $location = [];

        if ($this->city) {
            $location[] = $this->city;
        }

        if ($this->region && $this->region !== $this->city) {
            $location[] = $this->region;
        }

        if ($this->country) {
            $location[] = $this->country;
        }

        return !empty($location) ? implode(', ', $location) : 'Unknown';
    }

    /**
     * Get device info string.
     */
    public function getDeviceInfoAttribute()
    {
        $deviceInfo = [];

        if ($this->device) {
            $deviceInfo[] = $this->device;
        }

        if ($this->platform) {
            $deviceInfo[] = $this->platform;
        }

        if ($this->browser) {
            $deviceInfo[] = $this->browser;
        }

        return !empty($deviceInfo) ? implode(' - ', $deviceInfo) : 'Unknown';
    }
}
