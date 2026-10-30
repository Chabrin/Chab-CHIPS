<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class StaffOnboardingInvitation extends Model
{
    protected $fillable = [
        'recipient_email', 'token_hash', 'employment_type', 'department_id', 'position_id',
        'role_id', 'hire_date', 'zone_id', 'invited_by', 'status', 'submission',
        'expires_at', 'submitted_at', 'reviewed_by', 'reviewed_at', 'staff_id',
    ];

    protected $casts = ['expires_at' => 'datetime', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public function setSubmissionAttribute(?array $value): void
    {
        $this->attributes['submission'] = $value === null ? null : Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR));
    }

    public function getSubmissionAttribute(?string $value): ?array
    {
        return $value === null ? null : json_decode(Crypt::decryptString($value), true, 512, JSON_THROW_ON_ERROR);
    }
}
