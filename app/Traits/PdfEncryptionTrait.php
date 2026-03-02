<?php

namespace App\Traits;

use App\Models\SystemControl;
use Illuminate\Support\Facades\Log;

trait PdfEncryptionTrait
{
    public function shouldEncryptPdf($member = null): bool
    {
        try {
            // Check global encryption setting
            $encryptAllAttachments = SystemControl::where('setting', 'encrypt_all_member_attachments')->value('value');

            if ($encryptAllAttachments == '1') {
                return true;
            }

            // Check deposit notes encryption setting
            $encryptDepositNotes = SystemControl::where('setting', 'encrypt_deposit_notes')->value('value');

            if ($encryptDepositNotes == '1') {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Error checking encryption settings: ' . $e->getMessage());
            return false; // Default to no encryption if there's an error
        }
    }

    public function getEncryptionConfig($member = null): ?array
    {
        if (!$this->shouldEncryptPdf($member)) {
            return null;
        }

        // Use member number as password if available
        $password = $member && isset($member->member_no)
            ? $member->member_no
            : 'default_password';

        return [
            'user_password' => $password,
            'owner_password' => config('app.key', 'fallback_owner_password'),
            'permissions' => ['print', 'copy'],
            'mode' => 1 // 128-bit RC4 encryption
        ];
    }


    public function generateEncryptedPdf(string $viewName, array $data, $member = null, array $options = []): string
    {
        // Check if encryption should be applied
        $encryptionConfig = $this->getEncryptionConfig($member);

        if ($encryptionConfig) {
            $options['encryption'] = $encryptionConfig;
            Log::info('Generating encrypted PDF', [
                'view' => $viewName,
                'member_no' => $member->member_no ?? 'unknown',
                'encryption_enabled' => true
            ]);
        } else {
            Log::info('Generating non-encrypted PDF', [
                'view' => $viewName,
                'member_no' => $member->member_no ?? 'unknown',
                'encryption_enabled' => false
            ]);
        }

        return $this->generatePdf($viewName, $data, $options);
    }

    public function generateEncryptedFilename(string $prefix, $member = null, string $extension = 'pdf'): string
    {
        $firstName = $member->first_name ?? 'Member';
        $timestamp = now()->format('His');
        $isEncrypted = $this->shouldEncryptPdf($member);
        $suffix = $isEncrypted ? '_encrypted' : '';

        return "{$prefix}_{$firstName}_{$timestamp}{$suffix}.{$extension}";
    }
}
