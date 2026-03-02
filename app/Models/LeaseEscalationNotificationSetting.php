<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaseEscalationNotificationSetting extends Model
{
    protected $table = 'cb_lease_escalation_notification_settings';

    protected $fillable = [
        'notify_roles',
        'cancel_roles',
    ];

    protected $casts = [
        'notify_roles' => 'array',
        'cancel_roles' => 'array',
    ];

    /**
     * Always return the single settings row, creating it if it doesn't exist.
     */
    public static function instance(): self
    {
        return static::firstOrCreate([], [
            'notify_roles' => [],
            'cancel_roles' => [],
        ]);
    }
}
