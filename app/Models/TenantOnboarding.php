<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantOnboarding extends Model
{
    protected $table = 'tenant_onboarding_submissions';
    protected $guarded = [];
    protected $casts = [
        'payload' => 'encrypted:array',
        'documents' => 'array',
        'revision' => 'integer',
        'prepared_by' => 'integer',
        'effected_at' => 'datetime',
    ];

    public const STAGES = [
        'field_officer' => 'Field Officer',
        'zone_manager' => 'Zone Manager',
        'internal_auditor' => 'Internal Auditor',
        'assistant_auditor' => 'Assistant Auditor',
    ];

    public function statusLabel(): string
    {
        return match ($this->state) {
            'effected' => 'Confirmed and Effected',
            'changes_requested' => 'Returned for Correction',
            'rejected' => 'Rejected',
            default => 'Pending Approval · Awaiting '.self::STAGES[$this->stage],
        };
    }
}
