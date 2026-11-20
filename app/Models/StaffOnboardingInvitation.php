<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class StaffOnboardingInvitation extends Model
{
    /** Onboarding upload keys and the document type each one is filed under. */
    public const DOCUMENT_TYPES = [
        'national_id' => 'National ID / Passport',
        'kra_pin' => 'KRA PIN Certificate',
        'contract' => 'Signed Employment Contract',
        'cv' => 'Curriculum Vitae (CV)',
        'academic' => 'Academic Certificates & Transcripts',
        'helb' => 'HELB Compliance Certificate',
    ];

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

    /**
     * Submitted files keyed by their Add Staff form field ("documents.national_id", "photo", "signature").
     * Each entry has path, name, size and mime; documents also carry type_name.
     */
    public function submittedFiles(): array
    {
        $submission = $this->submission ?? [];
        $files = [];
        foreach ($submission['onboarding_documents'] ?? [] as $document) {
            $key = array_search($document['type_name'], self::DOCUMENT_TYPES, true);
            if ($key !== false) $files["documents.$key"] = $document;
        }
        if (!empty($submission['staff_photo_path'])) {
            $files['photo'] = ['path' => $submission['staff_photo_path'], 'name' => basename($submission['staff_photo_path']), 'mime' => null];
        }
        if (!empty($submission['signature_path'])) {
            $files['signature'] = ['path' => $submission['signature_path'], 'name' => basename($submission['signature_path']), 'mime' => null];
        }
        return $files;
    }
}
