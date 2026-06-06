<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantExitCommunication extends Model
{
    protected $table = 'cb_tenant_exit_communications';

    protected $fillable = [
        'exit_id',
        'tenant_id',
        'comm_type',
        'subject',
        'message_body',
        'email_address',
        'mobile_number',
        'template_used',
        'sent_email',
        'sent_sms',
        'send_errors',
        'sent_by',
        'sent_at',
    ];

    protected $casts = [
        'sent_email'  => 'boolean',
        'sent_sms'    => 'boolean',
        'send_errors' => 'array',
        'sent_at'     => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function exit(): BelongsTo
    {
        return $this->belongsTo(TenantExit::class, 'exit_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'sent_by');
    }
}
